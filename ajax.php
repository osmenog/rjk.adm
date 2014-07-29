<?php
//Сюда нужно добавить, чтобы в случае ошибки, ее текст не выводился.
header('Accept: application/json');
$action = (isset($_POST['action']) ? $_POST['action'] : '');
switch ($action) {
	case 'get_banlist_users': 
		echo '{"OK": 1}';
		
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
    echo '{"error": {"error_code": 1, "error_msg": "Invalid action"}}';
    break;
}
?>