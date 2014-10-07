<?php
include_once "config.php";
include_once "exceptions.php";

/**
* 
*/
class SyncProvider {
  private $sql_link;

  function __construct() {
    global $config;
    $db_config = $config ['rejik_db'];
    
    //Получаем настройки подключения к SQL
    if (isset($db_config[0])) $this->db_host = $db_config[0];
    if (isset($db_config[1])) $this->db_login = $db_config[1];
    if (isset($db_config[2])) $this->db_passwd = $db_config[2];
    if (isset($db_config[3])) $this->db_name = $db_config[3];
    if (isset($db_config[4])) $this->db_codepage = $db_config[4];
    
    //Пытаемся установить соединение с SQL
    @$mysqli = new mysqli($db_config[0], $db_config[1], $db_config[2], $db_config[3]);
    
    //В случае ошибки бросаем исключение
    if ($mysqli->connect_errno) {
        throw new mysql_exception ($mysqli->connect_error, $mysqli->connect_errno);
    }

    $this->sql_link = $mysqli;
  }

  function __destruct() {
    //Если было установлено соединение с SQL, то закрываем его
    if ($this->sql_link !== null) {
      $this->sql_link->close();
    }
  }

  public function add_url_to_queue ($action, $banlist, $url, $url_id=-1) {
  //Добавляем url в очередь синхронизации
    
    //Готовим запрос
    $query = "INSERT INTO syncronize_url SET `banlist_name`='$banlist', `url`='$url', `url_id`='$url_id', `action`='$action';";
    
    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение
    if (!$response) {
      throw new mysql_exception ($this->sql_link->error, $this->sql_link->errno);
    }

    return true;
  }
}

?>