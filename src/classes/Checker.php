<?php

class Checker
{
  /**
   * Статическая переменная, в которой мы
   * будем хранить экземпляр класса
   *
   * @var SingletonTest
   */
  protected static $_instance;

  private function __construct()
  {
  }

  public static function getInstance()
  {
// проверяем актуальность экземпляра
    if (null === self::$_instance) {
// создаем новый экземпляр
      self::$_instance = new self();
    }
// возвращаем созданный или существующий экземпляр
    return self::$_instance;
  }

  /**
   * Запускает механизм самопроверки
   * @param type $is_autochecker Указывает на то, кто запускает скрипт
   * @return type
   */
  public function start($is_autochecker = false)
  {
    global $config;
//Инициализация подключений к БД и логера
    $rejik = new rejik_worker ($config['rejik_db']);

    logger::init();

    $checker_db = $rejik->sql;

//Обьявляем пути до файлов
    $root_path = $_SERVER['DOCUMENT_ROOT'] . "/{$config['proj_name']}/";
    $b_path = $root_path . "banlists/";
    $u_path = $root_path . "users/";
    $l_full_path = $root_path . "cron/lastcheck.log";

    $dt = date("Y-m-d H:i:s");
//echo "Start...\n";

//Получаем список банлистов
    $banlists = $rejik->banlists_get_list();
    $error_flag = false;

//---------------------------------------------------------------------------------------------------
//Перебираем все банлисты
//---------------------------------------------------------------------------------------------------
    foreach ($banlists as $bl) {
      $b_file = $b_path . $bl . "/urls"; //Определяем полный путь до файла банлиста

//Если файл банлиста отсутствует, то выполняем запись сообщения в БД
      if (!file_exists($b_file)) {
//echo "[{$b_file}] not found!\n";
        if ($is_autochecker) {
          $login = "auto_checker";
        } else {
          $login = "";
        }

        Logger::add(34, "Checker не смог найти файл", $b_file, $dt, $login);
        $this->checker_db_insert($checker_db, $b_file, $dt, 'not found');
        $error_flag = true;
        continue;
      }

//Получаем значения контрольных сумм...
      $db_crc = strtolower($rejik->banlist_get_crc($bl)); // ... из БД
      $file_crc = strtolower(sha1_file($b_file));         // ... из файла

//Если контрольные суммы не совпадают, то выполняем запись сообщения в БД и делаем запись в лог
      if ($db_crc != $file_crc) {
//echo "[{$b_file}] checksum error!\n";
        $this->checker_db_insert($checker_db, $b_file, $dt, 'checksum error');
        Logger::add(32, "Checker выявил ошибку хэша в банлисте [$bl]", $bl, $dt);
        $error_flag = true;
      }
    }

//echo "Banlist check - ".(($error_flag) ? "ERROR" : "Ok")."\n";

//---------------------------------------------------------------------------------------------------
//Перебираем всех пользователей
//---------------------------------------------------------------------------------------------------
    foreach ($banlists as $bl) {
      $u_file = $u_path . $bl;

//Если файл со списком пользователей отсутствует, то выполняем запись сообщения в БД
      if (!file_exists($u_file)) {
//echo "[{$u_file}] not found!\n";
        Logger::add(34, "Checker не смог найти файл", $u_file, $dt);
        $this->checker_db_insert($checker_db, $u_file, $dt, 'not found');
        $error_flag = true;
        continue;
      }

//Получаем значения контрольных сумм...
      $db_crc = strtolower($rejik->banlist_get_user_crc($bl));
      $file_crc = strtolower(sha1_file($u_file));

//Если контрольные суммы не совпадают, то выполняем запись сообщения в БД и делаем запись в лог
      if ($db_crc != $file_crc) {
//echo "[{$u_file}] checksum error!\n";
        $this->checker_db_insert($checker_db, $u_file, $dt, 'checksum error');
        Logger::add(33, "Checker выявил ошибку хэша в списке пользователей [$bl]", $bl, $dt);
        $error_flag = true;
      }
    }
//echo "Userlist check - ".(($error_flag) ? "ERROR" : "Ok")."\n";

//Если в ходе выполнения НЕ БЫЛ активирован error_flag, то сообщаем, что все прошло успешно
    if (!$error_flag) {
      Logger::add(31, "Checker успешно выполнил проверку по расписанию", "", $dt);
    } else {
      Logger::add(35, "Checker завершил работу с ошибкой", "", $dt);
    }

    $rejik->closedb();

    $err_msg = ($error_flag == true) ? "ERROR" : "OK";
    $logfile_result = $this->create_logfile($l_full_path, $dt, $err_msg);

    //Если по какой то причине не можем создать файл проверки
    if (!$logfile_result) {
      //fixme Нужно добавить блок <html>, а то сообщения выводятся в виде кракозябр
      echo "<h1>Во время проверки произошла ошибка:</h1>\n";
      $err = error_get_last();
      echo "<p>".$err['message']."</p>\n";

      echo "<h2><a href='/{$config ['proj_name']}/index.php?action=selftest'><<< Назад</a></h2>";
      return False;
    }

    return True;
  }

  /**
   * Добавляет в БД запись о проверенном файле и результате проверки
   * @param type $sql
   * @param type $filename
   * @param type $dt
   * @param type $message
   * @return type В случае ошибки возвращаем False. Если все ОК - то True
   */
  private function checker_db_insert($sql, $filename, $dt, $message)
  {
    $filename = $sql->real_escape_string($filename);
    $query = "INSERT INTO `checker`(`file`, `lastcheck`, `msg`) VALUES ('$filename','$dt','$message');";
    $res = $sql->query($query);
    if (!$res) {
//echo "[{$res}] sql error: ".$sql->errno." ".$sql->error."\n";
      return false;
    }
    return true;
  }

  /**
   * Функция создает на диске лог-файл @full-path, и записывает туда строку сообщение
   * @param type $full_path Полный путь до лог-файла
   * @param type $dt Атрибут времени
   * @param type $message Сообщение
   * @return type Возвращает true в случае успеха или false если произошла ошибка
   */
  private function create_logfile($full_path, $dt, $message)
  {
    $hdl = @fopen($full_path, "w");
    if (!$hdl) return false;

    fwrite($hdl, $dt . " " . $message);
    fclose($hdl);
    return true;
  }

  private function __clone()
  {
  }
}

?>