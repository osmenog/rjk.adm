<?php
include_once "config.php";
include_once "log.php";
	//Класс proxy_worker
	//Конструктор - подключается к мускл и хранит обьект-дескриптор
	//деструктор - закрывает соединение к субд
	//Методы:
	// get_userinfo (nick) - возвращает информацию по пользователю в виде ассоциативного массива
	// get_userscount - возвращает количество пользователей
	// get_groupscount
	// get_groupslist
// -----------------------------------------------------------------------------------------------------------------------------------------------
class rejik_exception extends Exception {}
class mysql_exception extends rejik_exception {}
// -----------------------------------------------------------------------------------------------------------------------------------------------
class worker {
	protected $sql;
	
	protected $db_host		= '';
	protected $db_login		= '';
	protected $db_passwd	= '';
	protected $db_name		= '';
	protected $db_codepage	= '';

	//protected $charset_conv	= FALSE;

	public function __construct($db_config) {
		global $config;
		//db: [0 - хост, 1 - логин, 2- пасс, 3 - имя бд, 4 - кодировка]

		if (isset($db_config[0])) $this->db_host = $db_config[0];
		if (isset($db_config[1])) $this->db_login = $db_config[1];
		if (isset($db_config[2])) $this->db_passwd = $db_config[2];
		if (isset($db_config[3])) $this->db_name = $db_config[3];
		if (isset($db_config[4])) $this->db_codepage = $db_config[4];

		@$mysqli = new mysqli($this->db_host, $this->db_login, $this->db_passwd, $this->db_name);
		if ($mysqli->connect_errno) {
    		echo "Не удалось подключиться к MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
		}

		$this->sql = $mysqli;
		//print_r ($this->conf['hostaddr']);
	}
} //end of worker
// -----------------------------------------------------------------------------------------------------------------------------------------------
class proxy_worker extends worker {


	public function get_userscount () {
		$query_str = "SELECT Count(*) FROM squidusers\n";
		$res = $this->sql->query($query_str);
    	if (!$res) {
    		echo "[get_userscount] Не удалось выполнить запрос \"{$query_str}\"<br/>Код: ".$this->sql->errno." ".$this->sql->error;
    		return;
    	}
  
    	$row = $res->fetch_row();
    	$res->close();
    	return $row[0];
	}

    public function get_userinfo($nick) {	
    	//$this->sql->set_charset("utf8");
    	$this->sql->set_charset($this->db_codepage); //Устанавливаем кодировку соединения с БД Самса
    	
    	$query_str = "SELECT * FROM squidusers WHERE `nick`='$nick';";
    	$res = $this->sql->query($query_str);
    	if (!$res) {
    		echo "[get_userscount] Не удалось выполнить запрос \"{$query_str}\"<br/>Код: ".$this->sql->errno." ".$this->sql->error;
    		return FALSE;
    	}
    	
    	if ($res->num_rows==0) return FALSE;

    	return $res->fetch_assoc();
    }

    public function get_userslist() {
    	global $config;
    	//$this->sql->set_charset("utf8"); //Устанавливаем кодировку соединения с БД Самса
    	$this->sql->set_charset($this->db_codepage);

    	$response = $this->sql->query("SELECT * FROM squidusers");
    	if (!$response) echo "get_userslist. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    	
    	if ($response->num_rows == 0) return 0;

    	$res = array ();
		while ($row = $response->fetch_assoc()) {
    		if (isset($config['conv'])) {
    			$row['family'] = empty($row["family"]) ? '' : iconv($config['conv'][0], $config['conv'][1], $row['family']);
    			$row['name'] = empty($row["name"]) ? '' : iconv($config['conv'][0], $config['conv'][1], $row['name']);
    			$row['soname'] = empty($row["soname"]) ? '' : iconv($config['conv'][0], $config['conv'][1], $row['soname']);
			}
    		$res[] = $row;
		}

    	$response->close();
    	return $res;
    }

    public function is_user ($nick) {
    	//Функция возвращает TRUE, если пользователь существует в базе, или FALSE - если его там нет.
    	$response = $this->sql->query("SELECT * FROM squidusers WHERE nick='$nick';");
    	if (!$response) {
    		echo "is_user. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    		return FALSE;
    	}
    	
    	if ($response->num_rows == 0) {
    		return FALSE;
    	} else {
    		return TRUE;
    	}
    }
} //end of proxy worker
// -----------------------------------------------------------------------------------------------------------------------------------------------
class rejik_worker extends worker {
	// public $err_code = 0;
	// public $err_msg  = "";
	// private function set_error_state ($code = 0, $msg="") {
	// 	$this->$err_code = $code;
	// 	$this->$err_msg = $msg;
	// }

	// ==========================================================================================================================
	public function __construct ($db_config) {
		parent::__construct($db_config);
		$this->sql->set_charset("utf8"); //Устанавливаем кодировку соединения с БД Режика
		
		global $config;
		if ($config ['admin_log']==True) logger::init($this->sql); //Инициализируем логер
	}
	
	// ==========================================================================================================================
	// Работа с Категориями (Бан-Листами)
	// ==========================================================================================================================
	public function get_banlists ($raw_mode=false) {
		// Description ...: Возвращает массив банлистов с полной информацией о них или без.
		// Parameters ....: $raw_mode=[true|false] - вид возвращаемых данных.\
		// Return values .: Успех - Если $raw_mode=true возвращает массив обьектов [0] => Array ([id],[name],[short_desc],[full_desc])
		//						  - Если $raw_mode=false возвращает массив банлистов Array (bl1, bl2, ... )
		//                        - Возвращает 0, если список банлистов пустой
		//                  Неудача - возвращает исключение mysql_exception
		// -------------------------------------------------------------------------
		$response = $this->sql->query("SELECT * FROM banlists");
    	
    	//Если вышла ошибка
    	if (!$response) throw new mysql_exception($this->sql->error, $this->sql->errno);
    		   	
    	if ($response->num_rows == 0) return 0;
    	
    	$res = array ();
		if ($raw_mode) {
			while ($row = $response->fetch_assoc()) $res[] = $row;
		} else {
			while ($row = $response->fetch_assoc()) $res[] = $row['name'];	
		}

    	$response->close();
    	return $res;
	}

	public function get_banlist_info ($banlist) {
		// Description ...: Возвращает информацю по заданному бан листу
		// Parameters ....: $banlist - название бан-листа
		// Return values .: Успех - Возвращает массив [0] => Array ([id],[name],[short_desc],[full_desc])
		//                  	  - Возвращает 0, если список банлистов пустой
		//				  Неудача - Вызывает исключение если возникла ошибка
		// -------------------------------------------------------------------------
		try {
			// Возвращает информацю по заданному бан листу
			$bl = $this->get_banlists(true); //Получаем список всех банлистов

			if ($bl != 0)  {
				foreach ($bl as $value) {
					if ($value['name']==$banlist) return $value;
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
		
		return 0;
	}

	public function is_banlist($banlist){
		// Description ...: Проверяет, существует ли банлист в базе
		// Parameters ....: $banlist - название бан-листа
		// Return values .: Успех - Возвращает TRUE если бан-лист существует
		//                  	  - Возвращает FALSE если бан-лист отсутствует
		//				  Неудача - Вызывает исключение если возникла ошибка
		// -------------------------------------------------------------------------
		try {
			$banlists = $this->get_banlists();
			if ($banlists!=0 && array_search($banlist, $banlists)!==FALSE) return TRUE;
		} catch (Exception $e) { throw $e; }

		return FALSE;
	}

	public function get_banlist_users($banlist) {
		// Description ...: Возвращает массив пользователей к которым применяется банлист
		// Parameters ....: $banlist - название бан-листа
		// Return values .: Успех - Возвращает массив Array (nick1, nick2, ...)
		//                  	  - Возвращает 0, если список пользователей пустой
		//				  Неудача - Вызывает исключение если возникла ошибка
		// -------------------------------------------------------------------------
		$response = $this->sql->query("SELECT DISTINCT nick FROM users_acl WHERE `banlist`='{$banlist}'");
    	
    	if (!$response) throw new mysql_exception($this->sql->error, $this->sql->errno);
    	
    	if ($response->num_rows == 0) return 0;

    	$res = array ();
		while ($row = $response->fetch_assoc()) {
    		//$row['desc'] = empty($row["desc"]) ? '' : iconv($this->db_codepage, 'UTF-8', $row['desc']);
    		$res[] = $row['nick'];
		}

    	$response->close();
    	return $res;
	}

	public function get_banlist_urls($banlist) {
		//Получить массив УРЛов относящихся к заданному $banlist
		$response = $this->sql->query("SELECT url FROM urls WHERE `banlist`='{$banlist}'");
    	if (!$response) {
    		echo "get_banlist_urls. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    		return 0;
    	}
		
		if ($response->num_rows == 0) return 0;
		
    	$res = array();
    	while ($row = $response->fetch_row()) {
    		$res[] = $row[0];
		}

    	$response->close();
    	return $res;
	}
	
	// ==========================================================================================================================
	// Работа с Пользователями
	// ==========================================================================================================================
	public function get_user_acl ($nick) {
		//Функция возвращает массив бан-листов, доступ к которым разрешен пользоваьелю.
		// $query = "SELECT\n"
		//     . "a.name as `banlist`\n"
		//     . "FROM `users_acl`,`banlists` a\n"
		//     . "WHERE\n"
		//     . "banlist_id = a.id AND nick='{$nick}';";

		$query = "SELECT DISTINCT banlist FROM users_acl WHERE nick='{$nick}';";

		$response = $this->sql->query($query);

    	if (!$response) echo "get_user_acl. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    	
    	$res = array ();
    	if ($response->num_rows == 0) return $res;

		while ($row = $response->fetch_assoc()) {
    		//$row['desc'] = empty($row["desc"]) ? '' : iconv($this->db_codepage, 'UTF-8', $row['desc']);
    		$res[] = $row['banlist'];
		}

    	$response->close();
    	return $res;
	}

	public function add_user_acl ($user, $banlist) {
		//Функция добавляет доступ пользователю $user к банлисту $banlist
		
		
		//Проверяем, существует ли банлист
		if (!($this->is_banlist($banlist))) {
			echo "<div class='alert alert-danger'><b>Ошибка!</b> Банлист <b>$banlist</b> отсутствует в базе!</div>\n";
			return -2;
		}

		//Готовим запрос
		$query = "INSERT INTO users_acl SET `nick`='$user', `banlist`='$banlist';";
		$response = $this->sql->query($query);
    	if (!$response) {
    		echo "<div class='alert alert-danger'><b>Ошибка!</b> Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error."</div>\n";
    		return -1;
    	}

    	//Запись в лог
    	Logger::add (1, "{$user} added user access to {$banlist}");
	}

	public function remove_user_acl ($user, $banlist) {
		//Функция до
		//echo "<h3>\$banlists</h3>\n<pre>"; print_r($banlists); echo "</pre>";
	
		//Проверяем, существует ли банлист
		if (!($this->is_banlist($banlist))) {
			echo "<div class='alert alert-danger'><b>Ошибка!</b> Банлист <b>$banlist</b> отсутствует в базе!</div>\n";
			return -2;
		}

		//Готовим запрос
		$query = "DELETE FROM users_acl WHERE `nick`='$user' AND `banlist`='$banlist';";
		$response = $this->sql->query($query);
    	if (!$response) {
    		echo "<div class='alert alert-danger'><b>Ошибка!</b> Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error."</div>\n";
    		return -1;
    	}

    	//Запись в лог
    	Logger::add (2, "{$user} disabled user access to {$banlist}");
	}
	
	// ==========================================================================================================================
	// Функции импорта
	// ==========================================================================================================================
	public function import_db($csv_file_path) {
		$query_txt = "LOAD DATA INFILE '{$csv_file_path}' REPLACE INTO TABLE `urls` FIELDS TERMINATED BY ';' ENCLOSED BY '\"' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n' (`url`, `banlist`)" ;
		//echo "<h1>$query_txt</h1>\n";
		$response = $this->sql->query($query_txt);
    	if (!$response) {
    		echo "import_db. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    		return;
    	}
    	
    	echo "<p>В БД импортировано: ".$this->sql->affected_rows. " записей</p>\n";
    	//if ($response->num_rows == 0) return 0;
	}

} //end of rejik_worker
// -----------------------------------------------------------------------------------------------------------------------------------------------
function CheckSession () {
  //Проверяем, авторизован ли пользователь.
  global $config;
  
  //Стартуем сессию 
  session_name("sid");
  session_set_cookie_params (3600,"/{$config['proj_name']}/");
  session_start();
  
  //Проверяем, был ли залогинен пользователь
  if (!isset($_SESSION['auth']) || $_SESSION['auth'] == 0) {
    header("Location: /{$config ['proj_name']}/login.php"); // ... если нет, то ридеректим на страницу ввода пароля
  }
}

function GetClientIP () {
  // Определяет IP пользователя
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    $ip = $_SERVER['REMOTE_ADDR'];
  }
  return $ip;
}

function num_case ($num, $v1, $v2, $v3) {
	if ($num == 1) return $v1;
	if (($num >= 2 && $num <5) || ($num == 0)) return $v2;
	if ($num >= 5) return $v3;
}

// case "reconfigure":
// echo('<div id="right_frame_align">');
// $fp = fsockopen('localhost',8081);
// if($fp)                	    
// {
// fputs($fp, "reconfigure\n");
// $answer = fgets($fp);
// if(substr($answer,0,2) == "ok")
// {
// echo("<h2><b>Реконфигурирование SQUID выполнено успешно</b></h2>");
// }else
// {
// echo("<h2><b>Не удалось выполнить реконфигурирование SQUID.</b></h2><br>");
// echo("Возможные причины:<br><br>");
// echo("1. Процесс squid_reconfigure отверг команду reconfigure");
// }
// fclose($fp);
// }else
// {
// echo("<h2><b>Не удалось выполнить реконфигурирование SQUID.</b></h2><br>");
// echo("Возможные причины:<br><br>");
// echo("1. На сервере не запущен процесс squid_reconfigure");                		
// }
// echo('</div>');
// break;
?>