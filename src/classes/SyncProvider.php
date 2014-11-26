<?php

/**
 * 
 */
class SyncProvider
{
  private $sql_link;
  
  function __construct() {
    global $config;
    $db_config = $config['rejik_db'];

    date_default_timezone_set('Asia/Omsk');
     //За относительное устанавливаем Омское время
    
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
      throw new mysql_exception($mysqli->connect_error, $mysqli->connect_errno);
    }
    
    $this->sql_link = $mysqli;
  }
  
  function __destruct() {
    
    //Если было установлено соединение с SQL, то закрываем его
    if ($this->sql_link !== null) {
      $this->sql_link->close();
    }
  }
  
  public function add_url_to_queue($action, $banlist, $url, $url_id = - 1) {
    
    //Добавляем url в очередь синхронизации
    
    //Готовим запрос
    $today = date("Y-m-d H:i:s");
    $query = "INSERT INTO syncronize_url SET `banlist_name`='$banlist', `url`='$url', `url_id`='$url_id', `action`='$action', `action_time`='$today';";
    
    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение
    if (!$response) {
      throw new mysql_exception($this->sql_link->error, $this->sql_link->errno);
    }
    
    return true;
  }
  
  public function get_servers_list() {
    
    //Возвращает список серверов, который известен данному серверу
    
    //Готовим запрос
    global $config;
    $query = "SELECT `id`, `short_name`, `guid`, `address`, `sync_last_time` FROM sync_nodes WHERE `guid`!='{$config['server_UUID']}' AND `enabled`=TRUE;";
    
    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение
    if (!$response) {
      throw new mysql_exception($this->sql_link->error, $this->sql_link->errno);
    }
    
    //Если вписок серверов пустой
    if ($response->num_rows == 0) return 0;
    
    //Извлекаем данные
    $res = array();
    while ($tmp_row = $response->fetch_assoc()) $res[] = $tmp_row;
    
    //Очищаем запрос
    $response->free_result();
    
    return $res;
  }
  
  public function get_elem_from_last_sync($last_sync_time) {
    
    //Формирует массив элементов, созданных или удаленных, с момента последней синхронизации
    
    //Готовим запрос
    global $config;
    $query = "SELECT `id`, `banlist_name`, `url`, `url_id`, `action`, `action_time` FROM syncronize_url WHERE `action_time`>='{$last_sync_time}';";
    
    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение
    if (!$response) {
      throw new mysql_exception($this->sql_link->error, $this->sql_link->errno);
    }
    
    //Если вписок серверов пустой
    if ($response->num_rows == 0) return 0;
    
    //Извлекаем данные
    $res = array();
    while ($tmp_row = $response->fetch_assoc()) $res[] = $tmp_row;
    
    //Очищаем запрос
    $response->free_result();
    
    return $res;
  }
  
  public function add_url_to_pool($action, $action_time, $banlist, $url, $url_id = - 1, $received_from) {
    
    //Вобавляет полученные ссылки в ПУЛ входящих ссылок
    
    //Готовим запрос
    $today = date("Y-m-d H:i:s");
    $query = "INSERT INTO sync_url_pool 
              SET `banlist_name` = '$banlist',
                  `url`          = '$url',
                  `url_id`       = '$url_id',
                  `action`       = '$action', 
                  `action_time`  = '$action_time',
                  `sync_time`    = '$today',
                  `sync_from`    = '$received_from';";
    
    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение
    if (!$response) {
      throw new mysql_exception($this->sql_link->error, $this->sql_link->errno);
    }
    
    return true;
  }
}
?>