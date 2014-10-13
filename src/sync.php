<?php
include_once "config.php";
include_once "classes/Exceptions.php";
include_once "classes/SyncProvider.php";


try {
  @$action = $_GET['action']; //На время тестов

  switch ($action) {
    case 'start':
      syncronization_start();
      break;
    
    default:
      //если нет параметров GET или POST запросов, значит проверяем, есть ли JSON
      $input = file_get_contents('php://input');

      if (empty($input)) {
        break;
      }
      
      $input_decoded = json_decode($input, true);
      
      //Пытаемся декодировать JSON. Если пришла какаято фигня, то завершаем обработку
      if (json_last_error()!=JSON_ERROR_NONE) {
        throw new rejik_exception("JSON parser error: ".json_last_error_msg(),json_last_error());
      }
      
      //Если JSON распарсился, проверяем, пришел ли параметр 'action'
      if (!isset($input_decoded['action'])) {
        throw new rejik_exception("Request error: missing 'action'",-1);
      }
      
      //Обрабатываем действие
      syncronization_perform_action ($input_decoded['action'], $input_decoded);
      
      break;
  }
} catch (rejik_exception $e ){
  echo $e->get_json();
} catch (Exception $e){
  echo exception_get_json($e->getCode(), $e->getMessage());
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

  echo "<p>Запуск процесса синхронизации...</p>\n";

  global $config;
  try {
    //Инициализируем провайдер синхронизации
    $sync_provider = new SyncProvider();

    //Получаем известные нам сервера
    $servers_list = $sync_provider->get_servers_list();
 //echo "<pre>\n"; print_r($servers_list); echo "</pre>\n";
    if ($servers_list==0) {
      echo "<p>Список серверов пустой...выходим</p>\n";
      exit;
    }
    
    echo "<p>Количество известных серверов в базе: ".count($servers_list)."</p>\n";    

    //Для каждого сервера из списка инициируем обмен данными
    foreach ($servers_list as $server) {
        echo "<p>Устанавливаем связь с {$server['short_name']}</p>\n";    
  
        //Получаем время последней синхронизации с сервером
        $last_sync_time = $server['sync_last_time'];
        echo "<span>(время последней синхронизации: {$last_sync_time}</span><br/>\n";
        echo "<span>GUID: {$server['guid']})</span>\n";
        $now = date("Y-m-d H:i:s");
        
        //Получаем список элементов, накопившихся на сервере с момента последней синхронизации.
        $elem_list = $sync_provider->get_elem_from_last_sync ($last_sync_time);
        if ($elem_list==0) {
          echo "<p>На сервере нет изменений с момента последней синхронизации с {$server['short_name']}</p>\n";
          exit;
        }

        echo "<p>Количество локальных изменений, произосшедших с последней синхронизации c {$server['short_name']}: ".count($elem_list)."</p>\n";
        
 echo "<pre>\n"; print_r($elem_list); echo "</pre>\n";

        //Формируем JSON-объект
        $json_obj = array ('action'=>'sync', 'last_sync_time'=>$last_sync_time, 'from_uuid'=>$config['server_UUID'], 'to_uuid'=>$server['guid'], 'urls'=>[]);
        $json_obj['urls'] = $elem_list;
        $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
 //echo "<pre>\n"; echo $json_str; echo "</pre>\n";

        echo "<p>Отправляем локальные изменения на {$server['short_name']}...</p>\n";
        $response = curl_send ($server['address'], $json_str);

        echo "<p>Получен ответ:</p>\n";
        echo "<pre>\n"; print_r($response); echo "</pre>\n";        
        
    }

  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b>Вылетело исключение.<br/>[{$e->getCode()}] : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }

}

function syncronization_perform_action($action, $input_decoded) {
  //Обработать полученные данные

  switch ($action) {
    case 'sync':
      syncronization_process_request ($input_decoded);
      break;
    
    default:
      throw new InvalidArgumentException ("Нет такого действия: {$action}");
      break;
  }
}

function syncronization_process_request($input_decoded) {
  //Обработать входящий запрос
  global $config;
  
  //echo "<pre>"; print_r($input_decoded); echo "</pre>";

  try {
    //Инициализируем провайдер синхронизации
    $sync_provider = new SyncProvider();

    //Получаем известные нам сервера
    $servers_list = $sync_provider->get_servers_list();
    echo "<pre>\n"; print_r($servers_list); echo "</pre>\n";
      
    //Выполняем проверку подписи и наличие данного сервера в локальной базе

    //Сохраняем ссылки в пул

  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b>Вылетело исключение.<br/>[{$e->getCode()}] : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }

}

?>