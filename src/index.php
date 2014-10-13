<?php
	include_once "config.php";
	require_once "classes/Classes.php";

	global $config;

	//Проверяем, залогинен ли пользователь
	CheckSession();
	
?>

<!DOCTYPE html>
<html>
<head>
  <title>Rejik 2.0</title>
  <meta charset='UTF-8'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
  <link href="css/font-awesome.min.css" rel="stylesheet">
  
  <!--<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>-->
  <script src="js/jquery.min.js"></script>
  <script src='js/bootstrap.min.js'></script>
  <script src='js/jquery.twbsPagination.min.js'></script>
  <script src='js/app.js'></script> 
</head>
<body>

<!-- Панелька навигации -->
<div class="navbar navbar-default navbar-static-top">
	<div class="container">

	<div class="navbar-header">
		<a class="navbar-brand text-center" href="/<?php echo $config ['proj_name'];?>">REJIK<br/><small>web admin</small></a>
	</div>

	<div class="navbar-collapse collapse">
		<!-- Left Nav -->
		<ul class="nav navbar-nav">
		  <li><a href='?action=showusers'><span class="glyphicon glyphicon-user"></span> Пользователи</a></li>
		  
		  <li class="dropdown">
		  	<a href='#' class="dropdown-toggle" data-toggle="dropdown">
		  	  <span class="glyphicon glyphicon-lock"></span> Бан-листы <b class="caret"></b>
		    </a>
		  	<ul class="dropdown-menu" role='menu'>
		  	  <li><a href="?action=showbanlists"><span class="glyphicon glyphicon-list-alt"></span> Показать список</a></li>
		  	  <!--<li><a href="?action=newbanlist"><span class="glyphicon glyphicon-plus-sign"></span> Создать банлист</a></li>-->

		  	  <!--
		  	  <li><a href="#">Something else here</a></li>
		  	  <li class="divider"></li>
		  	  <li class="dropdown-header">Nav header</li>
		  	  <li><a href="#">Separated link</a></li>
		  	  <li><a href="#">One more separated link</a></li>
		  	  -->
		  	</ul>
		  </li>
		  
		  
		   <li><a href='?action=showjournal'><span class="glyphicon glyphicon-eye-open"></span> Журнал событий</a></li>
		  <!-- Будут доступны в будущем
       <li class='bg-warning'><a href='?action=showstats'><span class="glyphicon glyphicon-stats"></span> Статистика</a></li>
		   <li class='bg-warning'><a href='?action=showtasksman'><span class="glyphicon glyphicon-calendar"></span> Планировщик</a></li>
      -->
		  <li class='bg-success'><a href='?action=reconfigure'><span class="glyphicon glyphicon-send"></span> Применить конфигурацию</a></li>
		</ul>
		<!-- Right Nav -->
		<ul class="nav navbar-nav navbar-right">
      <?php
        echo "<li class='text-center' style='padding-top:6px'>\n";
        $st = CheckSelfTestResult();
        $tooltip = "rel='tooltip' data-title='Последняя проверка:\n{$st[0]} {$st[1]}' data-placement='bottom' data-container='body' data-trigger='hover focus'";
        if ($st[2]=="OK") {
          echo "<button id='checker_btn' class='btn btn-success btn-sm' onClick='location.href=\"?action=selftest\"' {$tooltip}><i class='fa fa-check-circle fa-2x'></i></button>\n";  
        } else {
          echo "<button id='checker_btn' class='btn btn-danger btn-sm' onClick='location.href=\"?action=selftest\"' {$tooltip}><i class='fa fa-exclamation-circle fa-2x'></i></button>\n";
        }
        echo "</li>\n";
      ?>
			<li class="text-center"><a><small>Вы вошли как: <u>Admin</u></small></a></li>
		</ul>
	</div>
	</div>
</div>

<!-- Основной контейнер -->
<div class="container wrapper">
	<?php
		if ($config['debug_mode']) {
			echo "<div class='debug'>\n";
			echo "<pre>"; print_r($_POST); echo "</pre>";
			echo "<pre>"; print_r($_GET); echo "</pre>";
			echo "</div>\n";	
		}
		print_layout();
	 ?>
</div>

<!-- Пока отключим
<div class='footer navbar-fixed-bottom'>
	<div class='container'><h6>ОАО Плюс Банк v0.0</h6></div>
</div>
-->
</body>
</html>

<?php
function print_layout () {
  global $config;
	//Так как обработчик сообщений аякс писать было впадлу, будет использована классическая отправка с формы.
	//Вначале обрабатываем данные в POST запросе, выполняем его p_action
	//Выполнение этого запроса предусматривает вывод окошечка с результатом выполнения.
	$p_action = (isset($_POST['p_action']) ? $_POST['p_action'] : '');
	switch ($p_action) {
		case 'set_user_acl': //Устанавливаем права пользователя на бан-листы
			//Проверяем корректность запроса. Актуальность данных будет проверяться где-то "внутри" (=
			//Под "корректностью" понимаем наличие всех входных параметров и заполненность данными (неважно какими).
			$user=isset($_POST['user']) ? mysql_real_escape_string($_POST['user']) : '';
			if (empty($user)) {
				echo "<div class='alert alert-danger'><b>Ошибка!</b> Не указан один из параметров</div>\n";
				break;
			}
		
			//Извлекаем список банлистов из тела запроса
			$banlists=array();
			foreach ($_POST as $key => $value) {
        if (strpos($key, 'bl_') !== false) {$banlists[substr($key, 3)] = $value;}
      }

			if (count($banlists)==0) {
				echo "<div class='alert alert-danger'><b>Ошибка!</b> В запросе не указаны банлисты.</div>\n";
				break;
			}

			set_user_acl ($user, $banlists); //Вызываем обработчик
			break;

		case 'newbanlist': //Создаем новый банлист
			break;// Данный функционал отключен

			//Проверяем входные данные
			if (!isset($_POST['bl_name']) || !isset($_POST['bl_shortdesc']) || !isset($_POST['bl_fulldesc'])) {
				echo "<div class='alert alert-danger'><b>Ошибка!</b> Не указан один из параметров</div>\n";
				break;
			}
			
			//Фильтруем значения переменных
			$bl_name = mysql_real_escape_string($_POST['bl_name']);
			$bl_shortdesc = mysql_real_escape_string($_POST['bl_shortdesc']);
			$bl_fulldesc = mysql_real_escape_string($_POST['bl_fulldesc']);

			//Вызываем API-функцию
			create_banlist ($bl_name, $bl_shortdesc, $bl_fulldesc);
			break;
	}

	$action = (isset($_GET['action']) ? $_GET['action'] : '');
	switch ($action) {
		case 'showusers':
			layout_showusers();
			break;
		
		case 'showbanlists':
			layout_showbanlists();
			break;

		/*
		case 'newbanlist':
			layout_newbanlist();
			break;
		*/
			
		case 'getuser': //Применение банлистов к пользователю
			$user = isset($_GET['user']) ? htmlspecialchars($_GET['user'], ENT_QUOTES) : '';
			if ($user=='') {
				echo "<h1>Ошибка входящего запроса! Не указано имя пользователя</h1>\n";
				break;
			}
			layout_getuser($user);
			break;

		case 'getbanlist':
			$banlist = isset($_GET['banlist']) ? htmlspecialchars($_GET['banlist'], ENT_QUOTES) : '';
			if ($banlist=='') {
				echo "<h1>Ошибка входящего запроса! Не указан банлист</h1>\n";
				break;
			}
			layout_getbanlist($banlist);
			break;

		case 'reconfigure':
			layout_reconfigure();
			break;

    case 'selftest':
      layout_selftest();
      break;
    
    case 'check':
      $checker = Checker::getInstance();
      $checker->start();

      header("Location: /{$config ['proj_name']}/index.php?action=selftest&result=successful");
      break;

    case 'showjournal':
      layout_showjournal();
      break;

		default:
			echo "<h1>action=$action</h1>\n";
			break;
	}
}

//Функции с префиксом layout_ отвечают за вывод содержимого для указанного действия (action)
function layout_showusers() {
  include "layout/layout.showusers.inc";
}

function layout_showbanlists() {
  include "layout/layout.showbanlists.inc";
}

function layout_newbanlist() {
  include "layout/layout.newbanlist.inc";
}

function layout_getuser ($nick) {
  include "layout/layout.getuser.inc";
}

function layout_getbanlist($banlist) {
	include "layout/layout.getbanlist.inc";
}

function layout_showjournal() {
  include "layout/layout.showjournal.inc";
}

function set_user_acl($user, $banlists) {
	//Функция выполняет назначение прав полюзователю
	global $config;
	$prx = new proxy_worker ($config['sams_db']);
	$rejik = new rejik_worker ($config['rejik_db']);

	//echo "<pre>"; print_r($banlists); echo "</pre>";

	//1. Проверяем, существует ли пользователь.
	if (!($prx->is_user($user))) {
		echo "<div class='alert alert-danger'><b>Ошибка!</b> Пользователь $user не найден в базе SAMS</div>\n";
		return -1;
	}

	//2. Получаем список бан-листов пользователя
	$user_banlists = $rejik->user_acl_get($user);

	//3. Удаляем дубликаты из входящего списка банлистов
	// В данном случае попадание сюда дубликатов невозможно,
	// т.к. данные передаются через форму, используя метод POST.
	// Если в запросе вдруг окажется две записи, то обработана будет только последняя.

	$result_log=''; //Сюда будут писаться результаты выполнения команд
	//Выполняем назначение прав
	foreach ($banlists as $key => $value) {
		switch ($value) {
			case 0:
				//Бан листы на удаление
				if (array_search($key, $user_banlists)!==FALSE) {
					$rejik->user_acl_remove($user, $key);
					$result_log.= "Банлист <i>$key</i> будет применяться к пользователю <i>$user</i><br/>\n";
				}
				break;
			
			case 1:
				if (array_search($key, $user_banlists)===FALSE) {
					$rejik->user_acl_add($user, $key); 
					$result_log.= "Банлист <i>$key</i> не будет применяться к пользователю <i>$user</i><br/>\n";
				}
				break;
			
			default:
				echo "<div class='alert alert-danger'><b>Ошибка!</b> Получен неверный параметр: [$key]=[$value]</div>\n";
				return -1;
		}
	}

	if ($result_log!='') echo "<div class='alert alert-success'>\n{$result_log}\n</div>\n";
}

function layout_selftest() {
  include "layout/layout.selftest.inc";
}

function layout_reconfigure() {
	include "layout/layout.reconfigure.inc";
}

function create_banlist ($name, $short_desc, $full_desc) {
	global $config;
	$rejik = new rejik_worker ($config['rejik_db']);
	try {
		if ($rejik->banlist_create($name, $short_desc, $full_desc)) {
			echo "<div class='alert alert-success'>Создание бан-листа <i>{$name}</i> успешно выполнено!</div>\n";
		}
	} catch (rejik_exception $e) {
		if ($e->getCode() == 0) {
			echo "<div class='alert alert-danger'><b>Ошибка!</b> Банлист <i>{$name}</i> уже существует</div>\n";	
		} else {
			echo "<div class='alert alert-danger'><b>Ошибка</b><br/>{$e->getCode()} : {$e->getMessage()}</div>\n";	
		}
	} catch (exception $e) {
		echo "<div class='alert alert-danger'><b>Ошибка</b>Вылетело исключение.<br/>{$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
	}

	return;
}

?>