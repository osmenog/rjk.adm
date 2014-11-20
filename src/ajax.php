<?php
include_once "config.php";
include_once "log.php";
include_once "classes/HealthPanel.php";
include_once "classes/Classes.php";
include_once "classes/Exceptions.php";
include_once "classes/API_worker.php";

global $config;

//register_shutdown_function("dbg_last_error");

function dbg_last_error() {
  $e = error_get_last();
  if ($e !== NULL) {
    if ($e['type'] == E_ERROR) {
      ob_clean();
      echo exception_get_json ($errno, $e['message'], 'E_ERROR'/*$e['type']*/, "Line: ".$e['line']);
    }
    ob_end_flush();
  }
}

//error_reporting(0);
//Сюда нужно добавить, чтобы в случае ошибки, ее текст не выводился.

//Вклчаем буфферизацию вывода. Скрипт ничего не отправит, пока не будет вызвано ob_end_flush()
ob_start ();

header('Accept: application/json');
$request_data = $_POST;
 //Источник данных

$action = (isset($request_data['action']) ? $request_data['action'] : '');
$api_version = (isset($_GET['v']) ? $_GET['v'] : 1);

try {
  $rjk = new rejik_worker($config['rejik_db']);
  $api = new api_worker($rjk, $api_version);
  $validated_data = $api::validate($request_data);
  
  switch ($action) {
    case 'banlist.getURLlist':
      $api->check_signature($request_data);
      $r = $api->banlist_getUrlListEx($validated_data['banlist'], $validated_data['offset'], $validated_data['limit']);
      echo $r;
      break;

    case 'banlist.addURL':
      $api->check_signature($request_data);
      $url = $validated_data['url'];
      if (strlen($url) == 0) throw new api_exception("URL не может быть пустым", -1);
      
      $r = $api->banlist_addurl($validated_data['banlist'], $url);
      echo $r;
      break;

    case 'banlist.removeURL':
      $api->check_signature($request_data);
      $r = $api->banlist_removeurl($validated_data['banlist'], $validated_data['id']);
      echo $r;
      break;

    case 'banlist.changeURL':
      $api->check_signature($request_data);
      $r = $api->banlist_changeurl($validated_data['banlist'], $validated_data['id'], $validated_data['url']);
      echo $r;
      break;

    case 'banlist.searchURL':
      $api->check_signature($request_data);
      $r = $api->banlist_searchurl($validated_data['banlist'], $validated_data['query']);
      echo $r;
      break;

    case 'logger.get':
      $api->check_signature($request_data);
      $r = $api->log_get($validated_data['offset'], $validated_data['limit']);
      echo $r;
      break;

    case 'server.check_availability':
      $r = $api->check_servers_availability();
      echo $r;
      break;

    default:
      throw new api_exception("Invalid action", 1);
      break;
    }
} catch(exception $e) {
  echo $e->get_json();
}



ob_end_flush();
?>