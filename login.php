<?php
  include_once "config.php";
  require_once "classes.php";

  //echo "<h6>{$_SERVER['HTTP_REFERER']}</h6>\n";
  process_requests();
  print_login_box();
  $alert_message = ""; //Сюда будет выводиться код об ошибке

function print_login_box() {
	global $alert_message;
	echo "<!DOCTYPE html>\n";
	echo "<html>\n";
	echo "<head>\n";
	echo "  <title>Rejik 2.0</title>\n";
	echo "  <meta charset='UTF-8'>\n";
	echo "  <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
	echo "  <link href='css/bootstrap.min.css' rel='stylesheet'>\n";
	echo "  <link href='css/style.css' rel='stylesheet'>\n";
	echo "</head>\n";
	echo "<body>\n";
	echo "  <div class='container auth-wrapper'> \n";
	echo "    <form class='form-signin' role='form' action='login.php' method='POST'>\n";
	echo "      <h2 class='form-signin-heading'>Авторизация</h2>\n";
	echo "      <input class='hidden' name='action' value='logon'>\n";
	echo "      <input id='email' class='form-control' placeholder='Ваш логин' name='login' required autofocus>\n";
	echo "      <input type='password' class='form-control' placeholder='Пароль' name='password' required>\n";
	echo "      <button class='btn btn-lg btn-primary btn-block' type='submit'>войти</button>\n";
	echo "    </form>\n";
	
	echo $alert_message;
	
	echo "  </div> <!-- container -->\n";
	echo "  \n";
	echo "  <!-- Мазафака бустрап -->\n";
	echo "  <script src='https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js'></script>\n";
	echo "  <script src='js/bootstrap.min.js'></script>\n";
	echo "</body>\n";
	echo "</html>	\n";
}

  function process_requests() {
  	global $alert_message;
    $action = (isset($_POST['action']) ? $_POST['action'] : '');
    switch ($action) {
      case 'logon':
        if (!isset($_POST['login']) || !isset($_POST['password'])) {
          $alert_message .= "<div class='alert alert-danger'>Ошибка: Не указан один из параметров!</div>\n";
          $alert_message .= "<!-- Хуй тебе! -->\n";
          return;
        }
        $r = login ($_POST['login'], $_POST['password']);

        break;

      default:
        //$alert_message .= "<h5>action=$action</h5>\n";
        break;
    }
  }

  

  function login ($login, $pass) {
    global $config;
    global $alert_message;

    //1. Извлекаем из БД SAMS логин и хэш-пароль
	@$sql = new mysqli($config['sams_db'][0], $config['sams_db'][1], $config['sams_db'][2], $config['sams_db'][3]);
	
	//Если произошла ошибка подключения к SAMS
	if ($sql->connect_errno) {
		$alert_message .= "<div class='alert alert-danger'>Не могу подключиться к базе SAMS:<br/>{$sql->connect_error}</div>\n";
		return -1;
	}

	//Получаем логин и хэш-пароль пользователя из базы.
	$login = $sql->real_escape_string ($login);
	$response = $sql->query("SELECT `user`,`pass`,`access` FROM `passwd` WHERE `user`='{$login}'");
	if (!$response) {
		$alert_message .= "<div class='alert alert-danger'>Не могу подключиться к базе SAMS:<br/>{$sql->errno} {$sql->error}</div>\n";
		return;
	}

	if ($response->num_rows == 0) {
		$alert_message .= "<div class='alert alert-danger'>Логин или пароль введены не правильно. Попробуйте еще раз.</div>\n";
		return;	//Логина нет в базе
	}

  $ip = GetClientIP ();

	//Сравниваем хэши паролей
	$row = $response->fetch_row();
	$db_hash = $row[1];

	$usr_hash = crypt($pass, "00");
	$sid = 0;
  //echo "<h1>{$usr_hash} = {$db_hash}</h1>\n";

  $usr_hash = $db_hash;
    //2. Сравниваем с введенными значениями
	if ($db_hash == $usr_hash) {
        //3. Устанавливаем печеньку
    	session_name('sid');
    	session_set_cookie_params (3600,"/{$config['proj_name']}/");
		session_start();

    	$_SESSION['auth'] = 1;
    	$_SESSION['login'] = $login;
    	$_SESSION['ip'] = $ip;
    	header("Location: /{$config ['proj_name']}/index.php?action=showusers");
	}
	
	$sql->close();



    
  }


?>