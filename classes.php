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
class rejik_exception extends Exception {
  function get_json(){
    //Функция возвращает JSON обьект содержащий параметры ошибки
    $obj = array('error' => array(
                          'error_type'  => get_class($this),
                          'error_code'  => $this->getCode(),
                          'error_msg'   => $this->getMessage(),
                          'error_trace' => $this->getTraceAsString()));
    return json_encode($obj);
  }
}
class mysql_exception extends rejik_exception {}
class api_exception extends rejik_exception {}
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
    		echo "[get_userinfo] Не удалось выполнить запрос \"{$query_str}\"<br/>Код: ".$this->sql->errno." ".$this->sql->error;
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
  public function banlists_get ($raw_mode=false) {
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
    //echo "<pre>"; print_r ($res); echo "</pre>\n";
    return $res;
  }

  public function banlist_create ($name, $short_desc, $full_desc='') {
    // Description ...: Создает новый банлист с заданными параметрами
    // Parameters ....: $name - Системное имя банлиста (должно быть в англ. раскладке)
    //             $shortdesc - Короткое описание 
    //              $fulldesc - Полное описание (не обязательно)
    // Return values .: Успех - Возвращает True
    //                Неудача - Возвращает False
    //                        - Возвращает исключение mysql_exception
    // -------------------------------------------------------------------------
  
    // 1. Проверяем, есть ли банлист с таким именем. Если есть - то исключение.
    if (array_search($name, $this->banlists_get())!==False) throw new rejik_exception("Banlist already exists",1);	
  
    // 2. Фильтруем XSS уязвимости
    $name = htmlspecialchars ($name);
    $short_desc = htmlspecialchars ($short_desc);
    $full_desc = htmlspecialchars ($full_desc);
  
    // 3. Выполняем запрос
    $query = "INSERT INTO banlists SET `name`='$name', `short_desc`='$short_desc', `full_desc`='$full_desc';";
    $response = $this->sql->query($query);
  
    // 3.1. Проверяем, выполнился запрос
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
  
    //Запись в лог
    Logger::add (3, "Banlist {$name} created");
    return True;
  }

  public function banlist_info ($banlist) {
    // Description ...: Возвращает информацю по заданному бан листу
    // Parameters ....: $banlist - название бан-листа
    // Return values .: Успех - Возвращает массив [0] => Array ([id],[name],[short_desc],[full_desc])
    //                  	  - Возвращает 0, если список банлистов пустой
    //				  Неудача - Вызывает исключение если возникла ошибка
    // -------------------------------------------------------------------------
    try {
    // Возвращает информацю по заданному бан листу
    $bl = $this->banlists_get(true); //Получаем список всех банлистов
  
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
    $banlists = $this->banlists_get();
    if ($banlists!=0 && array_search($banlist, $banlists)!==FALSE) return TRUE;
    } catch (Exception $e) { throw $e; }
  
    return FALSE;
  }

  public function banlist_get_users($banlist) {
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

  // ==========================================================================================================================
  // Работа со Ссылками
  // ==========================================================================================================================
  public function banlist_get_urls($banlist, $raw_mode=false, $offset=0, $length=0) {
    // Description ...: Возвращает массив УРЛов относящихся к заданному $banlist
    // Parameters ....: $banlist - название бан-листа
    //                  $raw_mode = [true|false]
    // Return values .: Успех - если $raw-mode =
    //                            true - будет возвращаться массив Array [][id,url]
    //                            false - будет возвращаться массив строк url
    //                  	  - Возвращает 0, если банлист не содержит УРЛы
    //				  Неудача - Вызывает исключение если возникла ошибка
    // -------------------------------------------------------------------------
  
    if ($offset!=0 or $length!=0) {
      //echo "<h3>$offset | $length</h3>";
      //Запрос со смещением
      $response = $this->sql->query("SELECT id, url FROM urls WHERE `banlist`='{$banlist}' ORDER BY id DESC LIMIT {$offset}, {$length}");
    } else {
      $response = $this->sql->query("SELECT id, url FROM urls WHERE `banlist`='{$banlist}' ORDER BY id DESC LIMIT");
    }
  
    if (!$response) throw new mysql_exception($this->sql->error, $this->sql->errno);
  
    if ($response->num_rows == 0) return 0;
  
    $res = array();
    while ($row = $response->fetch_row()) {
      //echo "<pre>"; print_r ($row); echo "</pre>";
      if ($raw_mode) {
        $res[] = $row;
      } else {
        $res[] = $row[1];
      }
    }
  
    $response->close();
    return $res;
  }

  public function banlist_urls_count ($banlist) {
    // Description ...: Возвращает количество УРЛов относящихся к заданному $banlist
    // Parameters ....: $banlist - название бан-листа
    // Return values .: Успех - Вернет число ссылок, привязанных к бан листу
    //                  	  - Возвращает 0, если банлист не содержит УРЛы
    //				  Неудача - Вызывает исключение если возникла ошибка
    // -------------------------------------------------------------------------
  
    $response = $this->sql->query("SELECT Count(*) FROM urls WHERE `banlist`='{$banlist}'");
    if (!$response) {
      echo "banlist_urls_count. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
      return 0;
    }
  
    if ($response->num_rows == 0) return 0;
    $urls_num = $response->fetch_row();
  
    $response->close();
    return $urls_num[0];
  }

  public function banlist_add_url ($banlist, $url) {
    //Добавляет URL в банлист
    // 1. Проверяем, есть ли банлист в базе. Если нет - то исключение.
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4);	
  
    $dup = $this->find_duplicate($url, $banlist);
    if ($dup!=0 and is_array($dup)) throw new rejik_exception("URL уже существует в банлисте {$banlist}",4); 

    $query = "INSERT INTO urls SET `url`='$url', `banlist`='$banlist';";
    $response = $this->sql->query($query);
  
    //2. Проверяем, выполнился запрос
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
  
    //Получаем ID созданой ссылки.
    $query = "SELECT `id` FROM `urls` WHERE `url`='$url' AND `banlist`='$banlist';";
    $response = $this->sql->query($query);
    $row = $response->fetch_assoc();
    //echo $row['id'];

    //3. Запись в лог
    Logger::add (3, "Added URL \'$url\' in banlist \'$banlist\'");
    return $row['id'];
  }

  public function banlist_change_url ($banlist, $id, $new_url_name) {
    //Изменяет заданный URL в банлисте
    // 1. Проверяем, есть ли банлист в базе. Если нет - то исключение.
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4); 
  
    // 2. Проверяем, есть ли URL с заданным ID базе
    // ДОБАВИТЬ!!!
  
    $query = "UPDATE urls SET `url`='{$new_url_name}' WHERE `id`={$id};";
    $response = $this->sql->query($query);
  
    //2. Проверяем, выполнился запрос
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
  
    //3. Запись в лог
    Logger::add (3, "URL #{$id} in banlist \'$banlist\' changed to \'{$new_url_name}\'.");
    return True;
  }

  public function banlist_remove_url ($banlist, $id) {
    //Удаляет заданный URL из банлиста

    // 1. Проверяем, есть ли банлист в базе. Если нет - то исключение.
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4); 
    
    $query = "DELETE FROM `urls` WHERE `banlist`='{$banlist}' AND `id`={$id}";
    $response = $this->sql->query($query);
  
    //2. Проверяем, выполнился запрос
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
  
    //3. Запись в лог
    Logger::add (3, "Remove URL #{$id} in banlist \'$banlist\'.");
    return True;
  }
  
  public function banlist_search ($banlist, $search) {
    /*Осуществляет поиск URL по маске в заданном банлисте*/

    $parsed_url = parse_url($search);
    if (!$parsed_url) return -1;

    $host = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
    
    $n_url = $host.$port.$path;
    //echo $n_url."\n";
    
    $parsed_arr = explode('.', $host);
    if ($parsed_arr[0]=='www') unset ($parsed_arr[0]);  //Убираем www
    array_splice($parsed_arr, 0, count($parsed_arr)-2); //Оставляем только домен 2-го уровня

    $host = implode('.', $parsed_arr);

    //echo $host."\n";

    $query = "SELECT * FROM urls WHERE `url` LIKE '%{$n_url}%';";
    $response = $this->sql->query($query);

    if ($response->num_rows == 0) return 0; //Если дубликатов нет, то выходим

    $res=[];
    while ($row = $response->fetch_row()) {
      $res[$row[0]] = $row[1];
      // * Пытаемся распарсить ссылку на:
    }
    //print_r ($res);
    return $res;
  }
  // ==========================================================================================================================
  // Работа с Пользователями
  // ==========================================================================================================================
  public function user_acl_get ($nick) {
    //Функция возвращает массив бан-листов, доступ к которым разрешен пользоваьелю.
    // $query = "SELECT\n"
    //     . "a.name as `banlist`\n"
    //     . "FROM `users_acl`,`banlists` a\n"
    //     . "WHERE\n"
    //     . "banlist_id = a.id AND nick='{$nick}';";
  
    $query = "SELECT DISTINCT banlist FROM users_acl WHERE nick='{$nick}';";
  
    $response = $this->sql->query($query);
  
    if (!$response) echo "user_acl_get. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
  
    $res = array ();
    if ($response->num_rows == 0) return $res;
  
    while ($row = $response->fetch_assoc()) {
    //$row['desc'] = empty($row["desc"]) ? '' : iconv($this->db_codepage, 'UTF-8', $row['desc']);
    $res[] = $row['banlist'];
    }
  
    $response->close();
    return $res;
  }

  public function user_acl_add ($user, $banlist) {
    //Функция добавляет доступ пользователю $user к банлисту $banlist

    //Проверяем, существует ли банлист
    if (!($this->is_banlist($banlist))) {
    echo "<div class='alert alert-danger'><b>Ошибка!</b> Банлист <b>$banlist</b> отсутствует в базе!</div>\n";
    return -2;
    }
    // Фильтрация XSS
    $user = htmlspecialchars($user);
    $banlist = htmlspecialchars($banlist);
  
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

  public function user_acl_remove ($user, $banlist) {
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
    echo "<h1>$query_txt</h1>\n";
    $response = $this->sql->query($query_txt);
    if (!$response) {
    echo "import_db. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    return;
    }
  
    echo "<p>В БД импортировано: ".$this->sql->affected_rows. " записей</p>\n";
    //if ($response->num_rows == 0) return 0;
  }

  // ==========================================================================================================================
  // Дополнительные функции
  // ==========================================================================================================================
  public function check_url ($url) {
    /*Проверяет, применяется по отношении к данной ссылки более глобальное правило*/

    $parsed_url = parse_url($url);
    if (!$parsed_url) return -1;

    $host = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
    
    $n_url = $host.$port.$path;
    echo $n_url."\n";

    
    $parsed_arr = explode('.', $host);
    if ($parsed_arr[0]=='www') unset ($parsed_arr[0]);  //Убираем www
    array_splice($parsed_arr, 0, count($parsed_arr)-2); //Оставляем только домен 2-го уровня

    $host = implode('.', $parsed_arr);

    echo $host."\n";

    $query = "SELECT * FROM urls WHERE `url` LIKE '{$parsed_url}%';";
    $response = $this->sql->query($query);

    if ($response->num_rows == 0) return 0; //Если дубликатов нет, то выходим

    $res=[];
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
      // * Пытаемся распарсить ссылку на:
    }

    print_r ($res);
  }

  public function find_duplicate($url, $banlist='') {
    $parsed_url = parse_url($url);
    if (!$parsed_url) return -1;

    $host = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
    
    $n_url = $host.$port.$path;
    if ($banlist=='') {
      $query = "SELECT * FROM urls WHERE `url`='$n_url';";
    } else {
      $query = "SELECT * FROM urls WHERE `banlist`='{$banlist}' AND `url`='$n_url';";
    }
    $response = $this->sql->query($query);

    if ($response->num_rows == 0) return 0; //Если дубликатов нет, то выходим

    $res=[];
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
      // * Пытаемся распарсить ссылку на:
    }

    return $res;
  }

} //end of rejik_worker
  
// -----------------------------------------------------------------------------------------------------------------------------------------------
class api_worker {
  protected $rejik;
  private $verison;

  public function __construct ($rejik, $version) {
    $this->$version=$version;

    if (is_object($rejik) && get_class($rejik)=='rejik_worker'){
      $this->rejik = $rejik;
    } else {
      throw new api_exception("Error Processing Request", 1);
    }
  }

  public static function validate ($data) {
    //Функция проверяет входные данные
    //Сюда нужно добавить поддержку проверки схемы
    //echo "<pre>"; print_r ($data); echo "</pre>\n";

    //Проверка на наличие ключевого свойства
    if (!isset($data['action']) or ($data['action']=='')) throw new api_exception ("Не указано свойство 'action'",2);
    if (!isset($data['sig']) or ($data['sig']=='')) throw new api_exception ("Не указано свойство 'sig'",2);

    foreach ($data as $k => $v) {
      if ($k=='offset' or $k=='limit') {
        if (!ctype_digit($data[$k])) throw new api_exception ("Атрибут '{$k}' должен иметь числовое значение",2);
      } 
      if ($k=='banlist') {
        if ($v=='') throw new api_exception ("Не указано свойство '{$k}'",2);
      }  
    }
    return $data;
  }

  public function check_signature($data) {
    $sig = $data['sig'];
    unset($data['sig']);
    ksort($data);
    //echo "<pre>"; print_r ($data); echo "</pre>\n";
    $str_data='';
    foreach ($data as $k=>$v) $str_data.=$k."=".$v;
    
    $md5_data=md5($str_data);
    //echo "<pre>"; print_r ($str_data); echo "</pre>\n";
    if ($sig!=$md5_data) throw new api_exception ("Полученная сигнатура не совпадает с рассчитаной: [{$md5_data}]",3);
  }

  public function banlist_addurl($banlist, $url) {
    try {
      $rjk = $this->rejik;
      $result = $rjk->banlist_add_url ($banlist, $url);

      $json_obj = array ('id' => $result);
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_changeurl($banlist, $url_id, $url) {
    try {
      $rjk = $this->rejik;
      $result = $rjk->banlist_change_url ($banlist, $url_id, $url);

      $json_obj = array ('result' => 1);
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_removeurl($banlist, $url_id) {
    try {
      $rjk = $this->rejik;
      $result = $rjk->banlist_remove_url ($banlist, $url_id);

      $json_obj = array ('result' => 1);
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      return $json_str;
      
    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_getUrlListEx($banlist, $offset=0, $limit=10) {
    // Description ...: Функция должна вернуть JSON обьект, содержащий ссылки по заданному смещению
    // Parameters ....: $banlist - имя банлиста
    //                  $offset - смещение, относительно начала
    //                  $limit - количество возвращаемых ссылок
    // Return values .: Успех - Возвращает JSON обьект, содержащий:
    //                          "banlist" - имя банлиста
    //                          "limit" - общее количество ссылок в банлисте
    //                          "total" - сколько ссылок передано в теле JSON обьекта
    //                          "offset" - возвращает смещение
    //                          "urls" - содержит обьект-ассоциативный массив, содержащий: {[ид ссылки] => [ссылка], ... }
    //                        - Если банлист не содержит ссылок, то все-равно будет возвращен JSON обьект, указанный выше.
    //                          При этом будет: length=0 и urls = []
    //                  Неудача - Будет вызвано исключение api_exception
    // -------------------------------------------------------------------------
    try {
      $rjk = $this->rejik;

      if (!$rjk->is_banlist($banlist)) throw new api_exception ("Banlist not found",3);
      
      $urls = $rjk->banlist_get_urls ($banlist, true, $offset, $limit);
      //echo "{$offset} | {$limit}";
      $json_obj = array ('banlist'=>$banlist,
                         'limit'=>$limit,
                         'offset'=>$offset,
                         'total'=>0,
                         'urls'=>[]);
  
      $urls_counter = 0; //Инициализируем счетчик для ссылок
      $urls_arr = array(); //Инициализируем массив для ссылок с ключами
      //Заполняем массив ссылками
      if ($urls!=0) {
        foreach ($urls as $key => $value) {
          $id= intval($value[0]);
          $url=$value[1];
          $urls_arr[$id]=$url;
          $urls_counter++;
        }
      }
      //$urls_arr = array('ya.ru/hi&a=1?b=2','<script>alert();</script>');
  
      $json_obj['urls']=$urls_arr;
      $json_obj['total']=$urls_counter;
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_searchurl($banlist, $query) {
    try {
      $rjk = $this->rejik;
      if (!$rjk->is_banlist($banlist)) throw new api_exception ("Banlist not found",3);

      $founded_urls = $rjk->banlist_search($banlist, $query);

      $json_obj = array ('total'=>0, 'urls'=>[]);
  
      $urls_counter = 0; //Инициализируем счетчик для ссылок
      $urls_arr = array(); //Инициализируем массив для ссылок с ключами
      //Заполняем массив ссылками
      if ($founded_urls!=0) {
        foreach ($founded_urls as $key => $value) {
          $id= intval($key);
          $url=$value;
          $urls_arr[$id]=$url;
          $urls_counter++;
        }
      }

      $json_obj['urls']=$urls_arr;
      $json_obj['total']=$urls_counter;
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }
}
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