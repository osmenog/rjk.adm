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
    
    date_default_timezone_set('Asia/Omsk'); //За относительное устанавливаем Омское время
    
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
    $today = date("Y-m-d H:i:s");
    $query = "INSERT INTO syncronize_url SET `banlist_name`='$banlist', `url`='$url', `url_id`='$url_id', `action`='$action', `action_time`='$today';";
    
    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение
    if (!$response) {
      throw new mysql_exception ($this->sql_link->error, $this->sql_link->errno);
    }

    return true;
  }

  public function get_servers_list() {
    //Возвращает список серверов, который известен данному серверу
    
    //Готовим запрос
    global $config;
    $query = "SELECT `id`, `guid`, `address`, `sync_last_time` FROM sync_nodes WHERE `guid`!='{$config['server_UUID']}';";

    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение   
    if (!$response) {
      throw new mysql_exception ($this->sql_link->error, $this->sql_link->errno);
    }

    //Если вписок серверов пустой
    if ($response->num_rows == 0) return 0;

    //Извлекаем данные
    $res = array ();
    while ($tmp_row = $response->fetch_assoc()) $res[] = $tmp_row;
    
    //Очищаем запрос
    $response->free_result();

    return $res;
  }

  public function get_elem_from_last_sync ($last_sync_time) {
    //Формирует массив элементов, созданных или удаленных, с момента последней синхронизации

    //Готовим запрос
    global $config;
    $query = "SELECT `id`, `banlist_name`, `url`, `url_id`, `action`, `action_time` FROM syncronize_url WHERE `action_time`>='{$last_sync_time}';";

    //Выполняем запрос
    $response = $this->sql_link->query($query);
    
    //Если ошибка, то выдаем исключение   
    if (!$response) {
      throw new mysql_exception ($this->sql_link->error, $this->sql_link->errno);
    }

    //Если вписок серверов пустой
    if ($response->num_rows == 0) return 0;

    //Извлекаем данные
    $res = array ();
    while ($tmp_row = $response->fetch_assoc()) $res[] = $tmp_row;
    
    //Очищаем запрос
    $response->free_result();

    return $res;
  }
}

@$action = $_GET['action']; //На время тестов

switch ($action) {
  case 'start':
    syncronization_start();
    break;
  
  default:
    # code...
    break;
}

function curl_send($address, $post_data) {
  //Инициализируем cURL и отправляем данные
  $curl_link = curl_init($address);

  $curl_options = array(
    /*
    CURLOPT_PROXY          => 'localhost',
    CURLOPT_PROXYPORT      => '8080', 
    */
    CURLOPT_HEADER         => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post_data,
    CURLOPT_RETURNTRANSFER => true,     // возвращает веб-страницу
    CURLOPT_CONNECTTIMEOUT => 120,      // таймаут соединения
    CURLOPT_TIMEOUT        => 120,      // таймаут ответа
    CURLOPT_HTTPHEADER     => array('Content-type: application/json')
  );
  curl_setopt_array  ($curl_link, $curl_options);

  $response = curl_exec($curl_link);    // выполняем запрос curl
  $header   = curl_getinfo( $curl_link ); //Получаем расширенную инфу о выполненном запросе

  //Если вылезла сетевая ошибка
  if (!$response) { 
    throw new Exception ("[curl_network] ".curl_error($curl_link),curl_errno($curl_link));
  }

  //Если вылезла логическая ошибка (например 404)
  if ($header['http_code']<>200) {
    throw new Exception ("[curl_http] Удаленный сервер вернул ошибку",$header['http_code']);  
  }
  
  curl_close($curl_link);

  return $response;
}

function syncronization_start() {
  //Пользователь запустил процесс синхронизации

  global $config;
  try {
    //Инициализируем провайдер синхронизации
    $sync_provider = new SyncProvider();

    //Получаем известные нам сервера
    $servers_list = $sync_provider->get_servers_list();
//echo "<pre>\n"; print_r($servers_list); echo "</pre>\n";

    //Для каждого сервера из списка инициируем обмен данными
    foreach ($servers_list as $server) {
        //Получаем время последней синхронизации с сервером
        $last_sync_time = $server['sync_last_time'];
        $now = date("Y-m-d H:i:s");
        
        //Получаем список элементов, накопившихся на сервере с момента последней синхронизации.
        $elem_list = $sync_provider->get_elem_from_last_sync ($last_sync_time);
//echo "<pre>\n"; print_r($elem_list); echo "</pre>\n";

        //Формируем JSON-объект
        $json_obj = array ('last_sync_time'=>$last_sync_time, 'from_uuid'=>$config['server_UUID'], 'to_uuid'=>$server['guid'], 'urls'=>[]);
        $json_obj['urls'] = $elem_list;
        $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
//echo "<pre>\n"; echo $json_str; echo "</pre>\n";

        $response = curl_send ($server['address'], $json_str);

        echo "<pre>\n"; print_r($response); echo "</pre>\n";        

        
    }

  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b>Вылетело исключение.<br/>[{$e->getCode()}] : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }
}



?>