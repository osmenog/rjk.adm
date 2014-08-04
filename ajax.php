<?php
  include_once "config.php";
  include_once "classes.php";
  include_once "log.php";

  global $config;

//Сюда нужно добавить, чтобы в случае ошибки, ее текст не выводился.
header('Accept: application/json');
$request_data = $_POST; //Источник данных

$action = (isset($request_data['action']) ? $request_data['action'] : '');
$api_version = (isset($_GET['v']) ? $_GET['v'] : 1);

try {
  $rjk = new rejik_worker ($config['rejik_db']);
  $api = new api_worker ($rjk, $api_version);  
  $validated_data = $api::validate($request_data);
  $api->check_signature($request_data);

  switch ($action) {
  	case 'banlist.geturllist': 
      $r = $api->banlist_getUrlListEx($validated_data['banlist'],
                                      $validated_data['offset'],
                                      $validated_data['limit']);
      echo $r;
  		break;

    case 'banlist.addurl': 
      $rjk->add_url($validated_data['banlist'],$validated_data['url']);
      echo '{"OK": 1}';
      break;

    case 'banlist.removeurl': 
      //sleep(5000);
      echo '{"OK": 1}';
      break;
    
    case 'banlist.changeurl': 
      $rjk->change_url ($validated_data['banlist'], $validated_data['id'], $validated_data['url']);
      echo '{"OK": 1}';
      break;
        
    default:
      throw new api_exception("Invalid action",1);
      break;
  }
} catch (exception $e) {
  echo $e->get_json();
}
?>