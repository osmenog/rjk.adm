<?php

//Получаем флаг подтверждения
$is_confirm = (isset($_GET['confirm']) ? $_GET['confirm'] : '');

//Если флаг = 1 то выполняем процедуру импорта, инача выводим форму настроек.
if ($is_confirm == 1) {
  $importer = import::getInstance();

  //Инициализируем переменные
  if (isset($_POST['bl_path']) && isset($_POST['usr_path'])) {
    $importer-> rejik_bl_path = $_POST['bl_path'];
    $importer->rejik_usr_path = $_POST['usr_path'];

    try {
      $importer->start();
    } catch (Exception $e) {
      //Если в процессе импорта произошла ошибка
      echo "<div class='alert alert-danger'>Произошла ошибка при выполнении импорта!</br>".$e->getMessage()."</div>";
    }
  } else {
    //Если нет одного из параметров, то выходим.
    echo "<div class='alert alert-warning'>Ошибка! Не передан один из параметров.</br>Процесс импорта остановлен.</div>";
  }

} else {
  print_form();
}

function print_form() {
  ?>
  <form role="form" action="?action=import&confirm=1" method="POST">
    <div class="form-group">
      <label for="bl_path">Путь до папки с бан-листами:</label>
      <input name="bl_path" type="text" class="form-control" id="bl_path" placeholder="Введите сюда абсолютный путь..."
             value="F:/xampp/htdocs/rjk/import/banlists/">
    </div>
    <div class="form-group">
      <label for="usr_path">Путь до папки с пользователями:</label>
      <input name="usr_path" type="text" class="form-control" id="usr_path"
             placeholder="Введите сюда абсолютный путь..." value="F:/xampp/htdocs/rjk/import/users/">
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

  public $rejik_bl_path = '';
  public $rejik_usr_path = '';

  private $csv_file_path;
  private static $csv_import_count = 0;

  public function start() {
    global $config;

    //Проверяем, существует ли csv файл с прошлого импорта
    $this->csv_file_path = $_SERVER['DOCUMENT_ROOT'] . "/".$config['proj_name']."/" . "import.csv";
    if (file_exists($this->csv_file_path)) {
      if (!unlink($this->csv_file_path)) {
        throw new Exception("Не могу удалить файл: ".$this->csv_file_path);
      }
    }

    //Получаем содержимое родительского каталога $bl_list
    $root_dir = $this->dir_contents($this->rejik_bl_path);
    if (!$root_dir) {
      throw new Exception("Немогу открыть каталог: ".$this->rejik_bl_path);
    }

    //Последовательно обходим каждый элемент, наход. в папке
    foreach ($root_dir as $k => $v) {
      //..обходим только директории
      if ($v == 'dir') {
        //Получаем содержимого дочернего каталога
        $child_dir = $this->rejik_bl_path.'/'.$k.'/';
        $tmp_dir_content = $this->dir_contents($child_dir);

        //Проверяем наличие файла urls
        if (!is_null($tmp_dir_content) && (array_key_exists('urls', $tmp_dir_content))) { // || array_key_exists('pcre', $tmp_dir_content)
          //и выполняем обработку файлов
          $this->import_urls_from_file($child_dir.'urls', $k);
        } else {
          throw new Exception ("Файл urls отсутствует в папке ".$child_dir);
          //continue;
        }
      }
    }

    echo "<h1>Импортировано в CSV файл: {$this::$csv_import_count} записей</h1>\n";

    //Импорт в SQL
    $this->import_db();
    return;
  }

  /**
   * Возвращает содержимое каталога в виде массива
   * @param $full_path полный путь до каталога
   * @return array|bool|null Возвращает массив обьекта [name] [file|dir]
   *                         Возвращает NULL если каталог пустой
   *                         Возвращает FALSE если каталога не существует
   */
  private function dir_contents($full_path) {
    if (is_dir($full_path) && $dh = opendir($full_path)) {
      $entry = array();
      while (($file = readdir($dh)) !== false) {
        if ($file != "." && $file != "..") {
          $fp = $full_path . $file;
          $entry[$file] = filetype($fp);
        }
      }
      if (count($entry) == 0) return NULL;

      closedir($dh);
      return $entry;
    } else {
      return FALSE;
    }
  }

  private function import_urls_from_file($file_path, $bl) {
    $res = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($res === false)
    {
      echo "Не могу открыть файл: {$file_path}\n";
      return FALSE;
    }

    //Открываем csv файл
    $hdl = fopen($this->csv_file_path, "a");
    if (!$hdl) {
      echo "Не могу открыть файл {$this->csv_file_path}\n";
      return FALSE;
    }

    //Колисество УРЛов в файле
    $this::$csv_import_count += count($res);

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
      if (strpos($v, ';') !== false) {
        echo "$v;$bl\n";
        continue;
      }
      $buffer .= "$v;$bl\n";
    }
    fwrite($hdl, $buffer);

    $csv_create_time_start = microtime(true) - $csv_create_time_start;
    echo "<h4>Банлист - {$bl}; Затраченное время: " . round($csv_create_time_start, 3) . "</h4>\n";
    fclose($hdl);
  }

  private function import_db() {

    // Инициируем соединение с режикомю
    global $config;
    $rejik = new rejik_worker($config['rejik_db']);
    $rejik->import_db($this->csv_file_path);
  }
}

?>
