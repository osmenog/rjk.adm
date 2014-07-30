<?php
  include_once "config.php";
  include_once "classes.php";
  include_once "log.php";

  global $config;

//Сюда нужно добавить, чтобы в случае ошибки, ее текст не выводился.
header('Accept: application/json');
$action = (isset($_POST['action']) ? $_POST['action'] : '');

try {
  $rjk = new rejik_worker ($config['rejik_db']);
  $api = new api_worker ($rjk);  

  switch ($action) {
  	case 'banlist.geturllist': 
  		$bl = isset($_POST['banlist']) ? $_POST['banlist'] : '';
      $os = isset($_POST['offset'] ) ? $_POST['offset']  : 0;
      $ln = isset($_POST['length'] ) ? $_POST['length']  : 0;
      $r = $api->banlist_getUrlListEx($bl, intval($os), intval($ln));
      echo $r;
  		break;

    case 'change_url': 
      //sleep(5000);
      echo '{"OK": 1}';
      break;

    case 'delete_url': 
      //sleep(5000);
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