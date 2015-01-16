<?php
//include_once "config.php";
//include_once "classes/Classes.php";

class Logger
{
  private static $sql;
  private static $last_crc;
  private static $last_id = 0;
  private static $is_init = False;
  private static $login;
  private static $worker;
  private static $events_count = 0;
  private static $is_cached = False;
  private static $last_error_msg = '';

  //--
  /*private static $tmp_hdl;
  private static $is_tmp_created = False;*/
  
  public static function get_length() {
    if (self::$is_init == False) return False;
    if (self::$is_cached) {
      return self::$events_count;
    } else {
      return self::count_log_events();
    }
  }
/*
  public function tmp_init() {
    $hdl = fopen("tmp.log", "a");
    self::$tmp_hdl = $hdl;
    self::$is_tmp_created = True;
  }*/
  
  public static function get_last_error() {
    return self::$last_error_msg;
  }
  
  public static function init() {
    global $config;
    
    if (isset($config['rejik_db'][0])) $db_host = $config['rejik_db'][0];
    if (isset($config['rejik_db'][1])) $db_login = $config['rejik_db'][1];
    if (isset($config['rejik_db'][2])) $db_passwd = $config['rejik_db'][2];
    if (isset($config['rejik_db'][3])) $db_name = $config['rejik_db'][3];
    //if (isset($config['rejik_db'][4])) $db_codepage = $config['rejik_db'][4];
    
    //Инициализируем подключение к БД
    $sql_con = new mysqli($db_host, $db_login, $db_passwd, $db_name);
    if ($sql_con->connect_errno) {
      self::$last_error_msg = "Не удалось подключиться к MySQL: (" . $sql_con->connect_errno . ") " . $sql_con->connect_error;
      return false;
    }
    $sql_con->set_charset("utf8");
    
    //Устанавливаем кодировку соединения с БД Режика
    self::$sql = $sql_con;
    
    //self::get_last_crc();
    self::$is_init = True;
    
    //echo self::$last_crc." - ".self::$last_id;
    return true;
  }
  
/*  public static function init_checker() {
    if (self::$is_init == False) {
      self::init();
    }
    self::$login = "auto_checker";
  }*/
  
  public static function stop() {
    if (self::$is_init) self::$sql->close();
    if (self::$is_tmp_created) fclose(self::$tmp_hdl);
  }
  
  public static function add($event_code, $event_msg, $event_attrib = "", $datentime = - 1, $printable_login = "") {
    
    // Если компонент еще не инициализироан, то прерываем работу и создаем сообщение об ошибке
    if (self::$is_init == False) {
      self::$last_error_msg = "Компонент logger не инициализирован!";
      return False;
    }
    
    $sql_obj = self::$sql;
    
    //Подготавливаем данные
    $crc = "none";
    
    //$crc = self::get_crc (array (self::$last_id + 1, $event_type, $message, self::$login, $ip));
    $ip = (!isset($_SERVER['HTTP_X_FORWARDED_FOR']) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_X_FORWARDED_FOR'];

    if ($printable_login != "") {
      $login = $printable_login;
    } else {
      $login = isset($_SESSION['login']) ? $_SESSION['login'] : "";
    }

    $query_str = "INSERT INTO `log` (`datentime`,`code`,`message`,`attribute`,`user_login`,`user_ip`,`crc`)
                      VALUES (" . ($datentime == - 1 ? "NOW()" : "'{$datentime}'") . ",
                      {$event_code},
                      '{$event_msg}',
                      '{$event_attrib}',
                      '{$login}',
                      '{$ip}',
                      '{$crc}');";
    
    $response = $sql_obj->query($query_str);
    
    //Если во время выполнения запроса произошла ошибка, то прерываем работу и создаем сообщение об ошибке
    if (!$response) {
      self::$last_error_msg = "Во время выполнения запроса произошла ошибка: ({$sql_obj->errno}) {sql_obj->error}";
      return False;
    }

    return True;
  }
  
  private static function count_log_events() {
    if (self::$is_init == False) {
      self::$last_error_msg = "Компонент logger не инициализирован!";
      return False;
    }
    
    $sql_obj = self::$sql;
    
    //Выполняем запрос, считающий количество записей
    $sql_res = $sql_obj->query("SELECT Count(*) FROM log;", MYSQLI_USE_RESULT);
    
    if (!$sql_res) {
      self::$last_error_msg = "Во время выполнения запроса произошла ошибка: ({$sql_obj->errno}) {sql_obj->error}";
      return False;
    }
    
    $row = $sql_res->fetch_row();
    
    self::$events_count = $row[0];
    self::$is_cached = true;
    
    $sql_res->free_result();
    
    return self::$events_count;
  }
  
  public static function get($start = 0, $len = 250) {
    if (self::$is_init == False) {
      self::$last_error_msg = "Компонент logger не инициализирован!";
      return False;
    }
    $sql_obj = self::$sql;
    
    self::count_log_events();
    
    //Подготавливаем запрос
    $query_str = "SELECT `id`,`datentime`,`code`,`message`,`attribute`,`user_login`,`user_ip`,`crc` FROM log ORDER BY id DESC LIMIT {$start}, {$len}";
    //echo "<pre>" . $query_str . "</pre>";
    
    $sql_res = $sql_obj->query($query_str, MYSQLI_USE_RESULT);
    if (!$sql_res) throw new mysql_exception($sql_obj->error, $sql_obj->errno);
    
    //echo "<pre>".$sql_res->num_rows."</pre>";
    //if ($sql_res->num_rows==0) return False;
    
    //Заполняем массив данными, полученными от SQL
    $result = array();
    while ($row = $sql_res->fetch_row()) {
      $result[] = $row;
    }
    
    $sql_res->free_result();
    
    //Если результат выборки пустой, то функция возвращает FALSE
    if (!empty($result)) {
      return $result;
    } else {
      return FALSE;
    }
  }
  
  /*public function tmp_write($msg) {
    if (self::$is_tmp_created) {
      fwrite(self::$tmp_hdl, $msg . "\r\n");
      return True;
    } else {
      return False;
    }
  }*/
  
   /* private function get_crc ($in){
   global $config;
   //sort ($in); //Сортируем по-возрастанию
  
   // Обьеденяем все параметры в одну строку
   $tmp = '';
   foreach ($in as $value) $tmp .= $value.".";
  
   // Добавляем предыдущий crc
   $tmp .= self::$last_crc;
   $out = md5 ($tmp);
   self::$last_crc = $out;
   if ($config['debug_mode']) echo "<h6>md5({$tmp})={$out}</h6>\n";
  
   return $out;
   }*/
  
   /*private function get_last_crc() {
   $sqli = self::$worker->sql;
  
   $res = $sqli->query("SELECT id,crc FROM log ORDER BY id DESC LIMIT 1;");
   if (!$res) throw new mysql_exception($this->sql->error, $this->sql->errno);
  
   $row = $res->fetch_row();
   $res->close();
  
   self::$last_id = $row[0];
   self::$last_crc = $row[1];
  
   //echo "<h6>set last_id=".self::$last_id." last_crc=".self::$last_crc."</h6>\n";
   }*/
}

class FileLogger {
  private static $hdl;
  private static $is_created = False;
  private static $dir_path = '';
  private static $is_throwable = True;

  public static function init($filename) {
    global $config;
    self::$dir_path = $config['log_dir'];

    if (!file_exists(self::$dir_path)) throw new logger_exception ("Дириктория ".self::$dir_path." не существует");

    self::$hdl = @fopen(self::$dir_path.$filename, "w"); //a
    $e = error_get_last();
    if (self::$hdl == False) throw new logger_exception("Ошибка открытия файла",$e);

    self::$is_created = True;
  }

  public static function add($msg, $event_attrib = "") {
    if (self::$is_created == False) return False;
    $res = fwrite(self::$hdl, $msg);
  }

  public static function close() {
    if (self::$is_created) fclose(self::$hdl);
  }
}
?>