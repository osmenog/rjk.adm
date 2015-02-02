<?php

/**
 * Interface db_connection
 */
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

  protected function __construct(){}
  protected function __clone(){}
  protected function __sleep(){}
  protected function __wakeup(){}

  // -------------------------------------------------------------------
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

    $this->db_link->set_charset("utf8"); //Устанавливаем кодировку соединения с БД Режика
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

//    if (!($res instanceof mysqli_result)) {
//      throw new mysql_exception ("bad query");
//    }

    if ($res->num_rows == 0) {
      return 0;
    } else {
      return $res;
    }
  }

  public function get_all_assoc($query_text) {
    $res = $this->_query($query_text);

//    if (!($res instanceof mysqli_result)) {
//      throw new mysql_exception ("bad query");
//    }

    if ($res->num_rows == 0) return 0;

    $res_assoc=array();
    while ($row = $res->fetch_assoc()) $res_assoc[] = $row;

    //Очищаем данные, полученные запросом.
    $res->close();

    return $res_assoc;
  }

  public function get_row($query_text) {
    $res = $this->_query($query_text);

//    if (!($res instanceof mysqli_result)) {
//      throw new mysql_exception ("bad query");
//    }

    $res_val = ($res->num_rows == 0) ? 0 : $res->fetch_row();
    $res->close();

    return $res_val;
  }

  public function get_row_assoc($query_text) {
    $res = $this->_query($query_text);

//    if (!($res instanceof mysqli_result)) {
//      throw new mysql_exception ("bad query");
//    }

    return ($res->num_rows == 0) ? 0 : $res->fetch_assoc();
  }

  public function query($query_text) {
    return $this->_query($query_text);
  }

  public function affected_rows(){
    return $this->db_link->affected_rows;
  }

  public function escape_string($text) {
    return $this->db_link->real_escape_string($text);
  }

  public function close_db() {
    if (isset($this->db_link)) @mysqli_close($this->db_link);
  }

}
?>