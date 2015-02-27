<?php
include_once "classes/HealthPanel.php";

//Получаем флаг подтверждения
$is_confirm = (isset($_GET['confirm']) ? $_GET['confirm'] : '');

//Проверяем, запущен ли процесс импорта на сервере, являющимся мастером
try {
  $m_id = (int) $_SESSION['master_id'];
  $m_av = $_SESSION['master_available'];
  $hp = new HealthPanel();
  $srvrs = $hp->get_servers();
  $l_id = $hp->get_current_id();

  if ($l_id != $m_id) {
    echo "<div class='alert alert-warning'>Процедуру импорта необходимо запускать на МАСТЕР-сервере (".print_server_hint($srvrs->get_server_by_id($m_id), $m_id).")</div>";
  } else {
    //Если флаг = 1 то выполняем процедуру импорта, инача выводим форму настроек.
    if ($is_confirm == 1) {

      $importer = import::getInstance();

      //Инициализируем переменные
      if (isset($_POST['bl_path']) && isset($_POST['usr_path'])) {
        $importer-> source_bl_dir = $_POST['bl_path'];
        $importer->source_usr_dir = $_POST['usr_path'];

        try {
          $importer->start();
        } catch (Exception $e) {
          //Если в процессе импорта произошла ошибка
          echo "<div class='alert alert-danger'><b>Произошла ошибка при выполнении импорта!</b></br>".$e->getMessage()." [".$e->getTraceAsString()."]</div>";
        }
      } else {
        //Если нет одного из параметров, то выходим.
        echo "<div class='alert alert-warning'><b>Ошибка! Не передан один из параметров.</b></br>Процесс импорта остановлен.</div>";
      }

    } else {
      print_form();
    }
  }

} catch (Exception $e) {
  echo "<div class='alert alert-danger'><b>Произошла ошибка при выполнении импорта!</b></br>".$e->getMessage()."</div>";
}


function print_form() {
  ?>
  <div class='alert alert-danger'><b>Внимание!!!</b> Список банлистов и пользовательских прав в БД Режик будут очищены с помощью 'TRUNCATE TABLE'</div>
  <form role="form" action="?action=import&confirm=1" method="POST">
    <div class="form-group">
      <label for="bl_path">Путь до папки с бан-листами:</label>
      <input name="bl_path" type="text" class="form-control" id="bl_path" placeholder="Введите сюда абсолютный путь..."
             value="/tmp/import/banlists/">
    </div>
    <div class="form-group">
      <label for="usr_path">Путь до папки с пользователями:</label>
      <input name="usr_path" type="text" class="form-control" id="usr_path"
             placeholder="Введите сюда абсолютный путь..." value="/tmp/import/users/">
    </div>
    <button type="submit" class="btn btn-default">Старт</button>
  </form>
<?php
}

/**
 * Created by PhpStorm.
 * User: td2
 * Date: 24.11.14
 * Time: 11:26
 */
class import {
  //Блок для синглтоновского паттерна -----------------------
  protected static $_instance;
  private function __construct() {  }
  private function __clone() {  }
  public static function getInstance() {
    // проверяем актуальность экземпляра
    if (null === self::$_instance) {
      // создаем новый экземпляр
      self::$_instance = new self();
    }
    // возвращаем созданный или существующий экземпляр
    return self::$_instance;
  }
  //---------------------------------------------------------

  //Пути до папок, из которых будем извлекать данные для БД Режика
  public $source_bl_dir     = '';
  public $source_usr_dir    = '';

  //Пути до промежуточных CSV файлов, содержащие импортируемые данные
  private $csv_bl_path      = '';  //Путь + имя CSV файла для импорта Урлов
  private $csv_usr_path     = ''; //Путь + имя CSV файла для импорта прав польз.

  private $bl_folders       = array(); //Список банлистов найденных в папке исторчика (Список папок в $source_bl_dir)

  //Статистические данные
  private static $csv_bl_imported_count  = 0;  //Количество УРЛОВ, имп. в CSV файл.
  private static $csv_usr_imported_count = 0; //Количество прав польз. имп. в CSV файл.

  //Функция - старт. Управляет процессом импорта
  public function start() {
    global $config;

    try {
      echo "<h2>Обработка банлистов</h2>";
      $this->import_urls();
      echo "<h3>В файл import_bl.csv импортировано {$this::$csv_bl_imported_count} записей</h3>\n";

      echo "<br/><h2>Обработка пользовательских прав</h2>";
      $this->import_users();
      echo "<h3>В файл import_usr.csv импортировано {$this::$csv_usr_imported_count} записей</h3>\n";

      echo "<br/><h2>Импорт банлистов в БД</h2>";
      $this->import_banlists_to_db();

      echo "<br/><h2>Импорт запрещенных URL адресов в БД</h2>";
      // Инициируем соединение с режикомю
      $master_cfg = HealthPanel::get_master_config();
      $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);
      $res = $rejik->import_from_csv($this->csv_bl_path, 'urls', array('url','banlist'));
      echo "<p>В БД импортировано: {$res} записей</p>\n";

      echo "<br/><h2>Импорт пользовательских привилегий в БД</h2>";
      // Инициируем соединение с режикомю
      $master_cfg = HealthPanel::get_master_config();
      $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);

      $res = $rejik->import_from_csv($this->csv_usr_path, 'users_acl', array('nick','banlist'));
      echo "<p>В БД импортировано: {$res} записей</p>\n";

      @unlink($this->csv_bl_path);
      @unlink($this->csv_usr_path);
    } catch (Exception $e) {
      throw $e;
    }
  }

  private function import_banlists_to_db() {
    global $config;

    //Если список банлистов каким-то образом не заполнился, то выводим ошибку
    if (count($this->bl_folders)==0) throw new Exception ("Список банлистов не получен!");

    //Подключаемся к БД режика
    $master_cfg = HealthPanel::get_master_config();
    $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);

    //По очереди перебираем список банлистов, и создаем их в базе
    foreach ($this->bl_folders as $v) {
      try {
        $rejik->banlist_create($v, $v, 'imported');
      } catch (Exception $e) {
        echo "<p>".$e->getMessage()."</p>";
      }
    }
  }

  private function import_urls () {
    global $config;

    //Проверяем, существует ли csv файл с прошлого импорта
    $this->csv_bl_path = $config['tmp_dir_path']."import_bl.csv";
    //if (file_exists($this->csv_bl_path)) {
      unlink($this->csv_bl_path);//) {
        //throw new Exception("Не могу удалить файл: ".$this->csv_bl_path);
      //}
    //}

    //Получаем содержимое родительского каталога $bl_list в виде массива: "имя обьекта" => "file|dir"
    $root_dir = $this->dir_contents($this->source_bl_dir);
    if (!$root_dir) {
      throw new Exception ("Немогу открыть каталог '".$this->source_bl_dir."'");
    }

    //Последовательно обходим каждый элемент, наход. в папке
    foreach ($root_dir as $k => $v) {
      //..обходим только директории
      if ($v == 'dir') {
        //Добавляем в название банлиста в массив
        $this->bl_folders[]= $k;

        //Получаем содержимого дочернего каталога
        $child_dir = $this->source_bl_dir.'/'.$k.'/';
        $tmp_dir_content = $this->dir_contents($child_dir);

        //Проверяем наличие файла urls ...
        if (!is_null($tmp_dir_content) && (array_key_exists('urls', $tmp_dir_content))) { // || array_key_exists('pcre', $tmp_dir_content)
          // ... и выполняем обработку 'urls' файлов
          $this->import_urls_from_file($child_dir.'urls', $k);
        } else {
          throw new Exception ("Файл urls отсутствует в папке ".$child_dir);
        }
      }
    }
    return;
  }

  private function import_users () {
    global $config;

    //Проверяем, существует ли csv файл с прошлого импорта
    $this->csv_usr_path = $config['tmp_dir_path']."import_usr.csv";
    if (file_exists($this->csv_usr_path)) {
      if (!unlink($this->csv_usr_path)) {
        throw new Exception("Не могу удалить файл: ".$this->csv_usr_path);
      }
    }

    //Получаем содержимое родительского каталога $usr_list
    try {
      $root_dir = $this->dir_contents($this->source_usr_dir);
    } catch (Exception $e) {
      throw $e;
    }

    //Последовательно обходим каждый элемент, наход. в папке
    foreach ($root_dir as $k => $v) {
      //..обходим только файлы!
      if ($v == 'file' && $k != '.htaccess') {
        $c = $this->import_users_from_file ("{$this->source_usr_dir}/{$k}", $k);
        echo "<p>Банлист {$k} содержит {$c} пользователей</p>";
      }
    }
  }
  /**
   * Возвращает содержимое каталога в виде массива
   * @param $full_path полный путь до каталога
   * @return array|bool|null Возвращает массив обьекта [name] [file|dir]
   *                         Возвращает NULL если каталог пустой
   *                         Возвращает FALSE если каталога не существует
   */
  private function dir_contents($full_path) {
    //На время отладки
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $dh = @opendir($full_path);
    if (!$dh) {
      $e = error_get_last();
      throw new Exception("Немогу открыть каталог: {$e['message']}");
    }

    $entry = array();
    while (($file = readdir($dh)) !== false) {
        if ($file != "." && $file != "..") {
          $fp = $full_path.$file;
          $entry[$file] = filetype($fp);
        }
    }

    if (count($entry) == 0) return NULL;

    closedir($dh);
    return $entry;
  }

  private function import_urls_from_file($file_path, $bl) {
    $res = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($res === false)
    {
      echo "Не могу открыть файл: {$file_path}\n";
      return FALSE;
    }

    //Открываем csv файл
    @$hdl = fopen($this->csv_bl_path, "a");
    if (!$hdl) {
      echo "Не могу открыть файл {$this->csv_bl_path}\n";
      return FALSE;
    }

    //Колисество УРЛов в файле
    $this::$csv_bl_imported_count += count($res);

    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    // Получаем URL
    // все, что идет после ? обрезаем.
    // кодируем URL
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    $csv_create_time_start = microtime(true);
    // Замеряем время
    //Добавляем все УРЛы в CSV файл
    $buffer = "";
    foreach ($res as $k => $v) {

      // Выполняем преобразование УРЛ. и сохраняем его в БД.
      $clean_url = filter_var($v, FILTER_SANITIZE_URL);
      //if ($clean_url === FALSE) {
      //echo "<p>$v;$bl</p>";
      //}

      if (strpos($clean_url, ';') !== false) {
        //echo "<p>$clean_url;$bl</p>";
        continue;
      }

      $buffer .= "$clean_url;$bl\n";
    }
    fwrite($hdl, $buffer);

    $csv_create_time_start = microtime(true) - $csv_create_time_start;
    echo "<h4>Банлист - {$bl}; Затраченное время: " . round($csv_create_time_start, 3) . "</h4>\n";
    fclose($hdl);
  }

  private function import_users_from_file($file_path, $bl) {
    $res = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($res === false) throw new Exception("Не могу открыть файл {$file_path}");

    //Считаем количество пользователей в списке
    $this::$csv_usr_imported_count += count($res);

    //Создаем csv файл
    $hdl = fopen($this->csv_usr_path, "a");
    if (!$hdl) throw new Exception ("Не могу создать файл {$this->csv_usr_path}");

    foreach ($res as $k=>$v) {
      fwrite($hdl, "{$v};{$bl}\n");
    }

    fclose($hdl);
    return count($res);
  }

  private function import_to_db() {
    global $config;

    try {
      // Инициируем соединение с режикомю
      $master_cfg = HealthPanel::get_master_config();
      $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);
      $res = $rejik->import_from_csv($this->csv_file_path, 'urls', array('url','banlist'));
    } catch (Exception $e) {
      throw $e;
    }
    echo "<p>В БД импортировано: {$res} записей</p>\n";
  }
}

?>
