<?php
  include_once "config.php";
  require_once "classes/RejikWorker.php";
  require_once "classes/Logger.php";
  require_once "classes/Classes.php";

  error_reporting(E_ALL);
  ini_set('display_errors', 0);
  
  function err_handler() {
    echo "<!DOCTYPE html>\n";
    echo "<html>\n";
    echo "<body>\n";

    $e = error_get_last();

    if ($e['type'] == E_NOTICE || $e['type'] == E_WARNING) return;

    if ($e !== NULL) {
      echo "<p>Произошла фатальная ошибка: ";
      //echo FriendlyErrorType($e['type'])."</p>";
      echo "<pre>\n";  var_dump ($e); echo "</pre>\n";
    }

    echo "</body>\n";
    echo "</html>\n";
    exit;
  }
  
  register_shutdown_function ('err_handler');

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
	echo "  <script src='js/jquery.min.js'></script>\n";
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
        $alert_message .= "<div class='alert alert-danger'>Ошибка: Не указан один из параметров!</div>\n<!-- Хуй тебе! -->\n";
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
  
  //Инициализируем логер
  if (!logger::init()) {
    if ($config['debug_mode']) {
        $alert_message .= "<div class='alert alert-warning'><b>Logger error</b> ".Logger::get_last_error()."</div>\n";
    }
  }

  //Устанавливаем соединение с REJIK DB для теста
  try {
    $rjk = new rejik_worker($config['rejik_db']);
  } catch (Exception $e) {
    $alert_message .= "<div class='alert alert-danger'>Не могу подключиться к REJIK DB";
    $alert_message .= ($config['debug_mode']) ? ":<br>{$e->getCode()} {$e->getMessage()}</div>\n" : ".<br> Для подробных сведений об ошибке, - включите режим отладки.</div>\n";
    return -1;
  }


  //1. Извлекаем из БД SAMS логин и хэш-пароль
  $sql = new mysqli($config['sams_db'][0], $config['sams_db'][1], $config['sams_db'][2], $config['sams_db'][3]);

  //Если произошла ошибка подключения к SAMS
  if ($sql->connect_errno) {
    $alert_message .= "<div class='alert alert-danger'>Не могу подключиться к базе SAMS";
    $alert_message .= ($config['debug_mode']) ? ":<br>{$sql->connect_error}</div>\n" : ".<br> Для подробных сведений об ошибке, - включите режим отладки.</div>\n";
    return -1;
  }
  
  //Получаем логин и хэш-пароль пользователя из базы.
  $login = $sql->real_escape_string ($login);
  $response = $sql->query("SELECT `user`,`pass`,`access` FROM `passwd` WHERE `user`='{$login}'");
  if (!$response) {
    $alert_message .= "<div class='alert alert-danger'>Не могу подключиться к базе SAMS";
    $alert_message .= ($config['debug_mode']) ? ":<br>{$sql->errno} {$sql->error}</div>\n" : ".<br> Для подробных сведений об ошибке, - включите режим отладки.</div>\n";
    return;
  }
  
  if ($response->num_rows == 0) {
    $alert_message .= "<div class='alert alert-danger'>Логин или пароль введены не правильно. Попробуйте еще раз.</div>\n";
    logger::add(3, "При идентификации был указан неверный логин [{$login}]", $login);
    return;	//Логина нет в базе. Но чтобы обмануть доверчивого юзера, говорим ему что-то про пароль.
  }

  $ip = GetClientIP ();
  
  //Сравниваем хэши паролей
  $row = $response->fetch_row();
  $db_hash = $row[1];

  $usr_hash = crypt($pass, "00");
  $sid = 0;
  
  // -!!!- ВНИМАНИЕ ---------------------------------------------------
  // - Эта строчка нужна только на момент отладки.
  //fixme ОПАСНОСТЕ!
  $usr_hash = $db_hash;
  // -!!!--------------------------------------------------------------

  //2. Сравниваем с введенными значениями
  if ($db_hash == $usr_hash) {
    //3. Устанавливаем печеньку
    session_name('sid');
    session_set_cookie_params (3600,"/{$config['proj_name']}/");
    session_start();
    
    $_SESSION['auth'] = 1;
    $_SESSION['login'] = $login;
    $_SESSION['ip'] = $ip;
    $_SESSION['server_verification'] = FALSE;

    logger::add(1, "Успешная аутентификация пользователя [{$login}]", $login);
    header("Location: /{$config ['proj_name']}/index.php?action=status");
  } else {
    $alert_message .= "<div class='alert alert-danger'>Логин или пароль введены не правильно. Попробуйте еще раз.</div>\n";
    logger::add(2, "При аутентификации пользователя [{$login}] был указан неверный пароль", $login);
    //$alert_message .= "<h4>{$usr_hash} = {$db_hash}</h4>\n";
    return; //А сейчас проблема в том, что не совпал пароль
  }
  
  $sql->close(); 
}

?>