<?php
include_once "config.php";

class logger {
	private static $sql;
	private static $last_crc;
	private static $last_id=0;

	private static $is_init = False;

	private static $login;

	public static function init ($sql_instance) {
		self::$sql = $sql_instance;
		self::get_last_crc();
		self::$is_init = True;
		//echo self::$last_crc." - ".self::$last_id;
		//Проверяем, авторизован ли рользователь

		self::$login = isset($_SESSION['login']) ? $_SESSION['login'] : "";
	}

	private function get_last_crc() {
		$sqli = self::$sql;
		
		$res = $sqli->query("SELECT id,crc FROM log ORDER BY id DESC LIMIT 1;");
		if (!$res) {
			echo "Не удалось выполнить запрос (" . $sqli->errno . ") " . $sqli->error;
			return;
		}
		
		$row = $res->fetch_row();
    	$res->close();

    	self::$last_id = $row[0];
    	self::$last_crc = $row[1];

    	//echo "<h6>set last_id=".self::$last_id." last_crc=".self::$last_crc."</h6>\n";
	}

	public function add ($event_type, $message) {
		if (self::$is_init == False) return;

		$sqli = self::$sql;

		$ip = (!isset($_SERVER['HTTP_X_FORWARDED_FOR']) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_X_FORWARDED_FOR'];

		//Подготавливаем данные
		$crc = self::get_crc (array (self::$last_id + 1, $event_type, $message, self::$login, $ip));
		$login = self::$login;
		
		$res = $sqli->query("INSERT INTO log VALUES (NULL, NOW(), {$event_type}, '{$message}', '{$login}', '{$ip}', '{$crc}');");
		if (!$res) {
			echo "Не удалось выполнить запрос (" . $sqli->errno . ") " . $sqli->error;
			return;
		}

		self::$last_id .= 1; 		
	}

	private function get_crc ($in){
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
	}
}
?>