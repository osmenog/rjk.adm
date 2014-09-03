<?php
include_once "config.php";
include_once "classes.php";

class logger {
	private static $sql;
	private static $last_crc;
	private static $last_id=0;
	private static $is_init = False;
	private static $login;
	private static $worker;

	public static function init () {
		global $config;

		//Инициализируем подключение к БД
		self::$worker = new worker($config ['log_db']);
		//self::get_last_crc();
		self::$is_init = True;
		//echo self::$last_crc." - ".self::$last_id;
		
		//Проверяем, авторизован ли рользователь
		self::$login = isset($_SESSION['login']) ? $_SESSION['login'] : "";
	}

	public static function stop () {
		self::$worker->closedb();
	}

	public function add ($event_code, $event_msg, $event_attrib="", $datentime=-1) {
		if (self::$is_init == False) return False;
		$sql_obj = self::$worker->sql;

		//Подготавливаем данные
		$crc = "none";
		//$crc = self::get_crc (array (self::$last_id + 1, $event_type, $message, self::$login, $ip));
		$ip = (!isset($_SERVER['HTTP_X_FORWARDED_FOR']) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_X_FORWARDED_FOR'];
		$login = self::$login;
		
		$query_str = "INSERT INTO `log` (`datentime`,`code`,`message`,`attribute`,`user_login`,`user_ip`,`crc`)
									VALUES (".($datentime==-1 ? "NOW()" : $datentime).",
												 {$event_code},
												 '{$event_msg}',
												 '{$event_attrib}',
												 '{$login}',
												 '{$ip}',
												 '{$crc}');";
		
		$response = $sql_obj->query($query_str);
 		if (!$response) throw new mysql_exception($sql_obj->error, $sql_obj->errno);

		//self::$last_id .= 1;
	}

/*	private function get_crc ($in){
		global $config;
		//sort ($in); //Сортируем по-возрастанию
		
		// Обьеденяем все параметры в одну строку
		$tmp = ''; 
		foreach ($in as $value) $tmp .= $value.".";

		// Добавляем предыдущий crc
		$tmp .= self::$last_crc;
		$out = md5 ($tmp);		
		self::$last_crc = $out;
		if ($config['debug_mode']) echo "<h6>md5({$tmp})={$out}</h6>\n";
		
		return $out;
	}*/

/*	private function get_last_crc() {
		$sqli = self::$worker->sql;
		
		$res = $sqli->query("SELECT id,crc FROM log ORDER BY id DESC LIMIT 1;");
		if (!$res) throw new mysql_exception($this->sql->error, $this->sql->errno);
		
		$row = $res->fetch_row();
    	$res->close();

    	self::$last_id = $row[0];
    	self::$last_crc = $row[1];

    	//echo "<h6>set last_id=".self::$last_id." last_crc=".self::$last_crc."</h6>\n";
	}*/

}
?>