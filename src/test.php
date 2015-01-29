<?php

USE test;

include_once "config.php";
include_once "classes/Exceptions.php";

interface db_connection {
  function query($query_text);
  function affected_rows();
  //function close_db();
}

abstract class mysql_connection implements db_connection {

  protected static $instances = array();

  protected $db_link;
  protected $db_host;
  protected $db_login;
  protected $db_passwd;
  protected $db_name;
  protected $db_codepage;

  public static function getInstance($config) {
    //Получаем имя вызывающего класса
    $className = static::getClassName();

    //Если вызывающий класс не был добавлен ранее в массив, то...
    if ( !isset(self::$instances[$className]) || !(self::$instances[$className] instanceof $className) ) {
      //.. добавляем в статический массив
      self::$instances[$className] = new $className();

      //Пробуем инициализировать подключение к БД
      try {
        self::$instances[$className]->_init($config);
      } catch (Exception $e) {
        // Если подключение не удалось, то удаляем массив и пересылаем исключение дальше.
        unset (self::$instances[$className]);
        throw $e;
      }

    }
    return self::$instances[$className];
  }

  final protected static function getClassName()
  {
    return get_called_class();
  }

  protected function _init($db_config) {
    if (isset($db_config[0])) $this->db_host = $db_config[0];
    if (isset($db_config[1])) $this->db_login = $db_config[1];
    if (isset($db_config[2])) $this->db_passwd = $db_config[2];
    if (isset($db_config[3])) $this->db_name = $db_config[3];
    if (isset($db_config[4])) $this->db_codepage = $db_config[4];

    $this->db_link = @new mysqli($this->db_host, $this->db_login, $this->db_passwd, $this->db_name);

    if ($this->db_link->connect_errno) {
      throw new mysql_exception($this->db_link->connect_error, $this->db_link->connect_errno);
    }
  }



  private function _query($query_text){

    $res = $this->db_link->query($query_text);
    //Возвращает FALSE в случае неудачи.
    //В случае успешного выполнения запросов SELECT, SHOW, DESCRIBE или EXPLAIN mysqli_query() вернет объект mysqli_result.
    //Для остальных успешных запросов mysqli_query() вернет TRUE.

    if ($res === FALSE) {
      throw new mysql_exception ($this->db_link->error, $this->db_link->errno);
    }

    return $res;
  }

  public function get_all($query_text) {
    $res = $this->_query($query_text);

    if (!($res instanceof mysqli_result)) {
      throw new mysql_exception ("bad query");
    }

    return $res;
  }

  public function get_row($query_text) {
    $res = $this->_query($query_text);

    if (!($res instanceof mysqli_result)) {
      throw new mysql_exception ("bad query");
    }

    return ($res->num_rows == 0) ? 0 : $res->fetch_row();
  }

  public function get_row_assoc($query_text) {
    $res = $this->_query($query_text);

    if (!($res instanceof mysqli_result)) {
      throw new mysql_exception ("bad query");
    }

    return ($res->num_rows == 0) ? 0 : $res->fetch_assoc();
  }

  public function query($query_text) {
    return $this->_query($query_text);
  }

  public function affected_rows(){

  }

  public function close_db() {
    if (isset($this->db_link)) @mysqli_close($this->db_link);
  }

  protected function __construct(){}
  protected function __clone(){}
  protected function __sleep(){}
  protected function __wakeup(){}
}

class master_connect extends mysql_connection {

}
class slave_connect extends mysql_connection {

}

class worker {

  protected $master;
  protected $slave;

  public function __construct() {
    global $config;
    $config ['sams_db'] = array('localhost', '1', '2', 'rejik', 'utf8');

    $this->slave = slave_connect::getInstance($config['rejik_db']);

    try {
      $this->master = master_connect::getInstance($config['sams_db']);
    } catch (Exception $e) {
      echo "[m] ".$e->getMessage();
      $this->master = & $this->slave;
    }

  }

  public function __destruct() {
    if (isset($this->slave)) {
      $this->slave->close_db();
    }
    if (isset($this->master)) {
      $this->master->close_db();
    }
  }
}

class banlists_worker extends worker {
  public function banlists_get ($raw_mode=false) {
    //$response = $this->master->query("INSERT * FROM banlists");
    $response = $this->slave->query("SELECT * FROM users");
    //$response = $this->master->query("SELECT * FROM banlists");

    //if ($response->num_rows == 0) return array();

    $res = array ();
    if ($raw_mode) {
      while ($row = $response->fetch_assoc()) $res[] = $row;
    } else {
      while ($row = $response->fetch_assoc()) $res[] = $row['name'];
    }

    $response->close();
    echo "<pre>"; print_r ($res); echo "</pre>\n";
    return $res;
  }
}

//-----------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

function err_handler() {
  $e = error_get_last();
  if ($e !== NULL) {
    echo "<pre>\n";  var_dump ($e); echo "</pre>\n";
    echo FriendlyErrorType($e['type']);
  }
}
function FriendlyErrorType($type)
{
  switch($type)
  {
    case E_ERROR: // 1 //
      return 'E_ERROR';
    case E_WARNING: // 2 //
      return 'E_WARNING';
    case E_PARSE: // 4 //
      return 'E_PARSE';
    case E_NOTICE: // 8 //
      return 'E_NOTICE';
    case E_CORE_ERROR: // 16 //
      return 'E_CORE_ERROR';
    case E_CORE_WARNING: // 32 //
      return 'E_CORE_WARNING';
    case E_COMPILE_ERROR: // 64 //
      return 'E_COMPILE_ERROR';
    case E_COMPILE_WARNING: // 128 //
      return 'E_COMPILE_WARNING';
    case E_USER_ERROR: // 256 //
      return 'E_USER_ERROR';
    case E_USER_WARNING: // 512 //
      return 'E_USER_WARNING';
    case E_USER_NOTICE: // 1024 //
      return 'E_USER_NOTICE';
    case E_STRICT: // 2048 //
      return 'E_STRICT';
    case E_RECOVERABLE_ERROR: // 4096 //
      return 'E_RECOVERABLE_ERROR';
    case E_DEPRECATED: // 8192 //
      return 'E_DEPRECATED';
    case E_USER_DEPRECATED: // 16384 //
      return 'E_USER_DEPRECATED';
  }
  return "";
}
function main() {
  register_shutdown_function ('err_handler');

  //Проверяем, подключен ли модуль mysqli
  echo "<p>Проверка на существование класса <b>mysqli</b>: ";
  echo (class_exists('mysqli') == TRUE) ? 'ok' : 'error';
  echo "</p>\n";

  //Проверяем, подключен ли модуль filter_vars
  echo "<p>Проверка на существование функции <b>filter_input</b>: ";
  echo (function_exists('filter_input') == TRUE) ? 'ok' : 'error';
  echo "</p>\n";

  //Значения глобальных переменных
  echo "<p>display_errors: "; var_dump (ini_get('display_errors')); echo "</p>\n";

  echo "<p>error_reporting: ".ini_get('error_reporting')."</p>\n";

  echo "<p>max_execution_time: ".ini_get('max_execution_time')."</p>\n";

  include "config.php";
  global $config;

  $s = new mysqli("localhost", "rejik_adm", "admin3741", "rejik");
  $s->query("Show slave status;");
  print_r($s);
  //Значения глобальных переменных
  echo "<p>display_errors: "; var_dump (ini_get('display_errors')); echo "</p>\n";

  echo "<p>error_reporting: ".ini_get('error_reporting')."</p>\n";

  echo "<p>max_execution_time: ".ini_get('max_execution_time')."</p>\n";

  $tmp = $_SERVER['DOCUMENT_ROOT']."/rjk/";
  echo "<p>$tmp</p>";
  $tmp2 = fileperms($tmp);
  var_dump ($tmp2);
}

//main();

try {
  $bl = new banlists_worker();
  $bl->banlists_get();

} catch (Exception $e) {
  echo "[g] ".$e->getMessage();
}
?>