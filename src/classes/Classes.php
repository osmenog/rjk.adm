<?php
include_once "config.php";
include_once "classes/Exceptions.php";
include_once "classes/Logger.php";
include_once "classes/SyncProvider.php";
include_once "classes/Checker.php";

class worker {
	public $sql;
	
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
      throw new mysql_exception($mysqli->connect_error, $mysqli->connect_errno);
		}

		$this->sql = $mysqli;
	}

  public function closedb(){
    if ($this->sql !== Null) {
      $this->sql->close();
    }
  }
} //end of worker
// -----------------------------------------------------------------------------------------------------------------------------------------------
class proxy_worker extends worker {

	public function get_userscount () {
		$query_str = "SELECT Count(*) FROM squidusers\n";
		$res = $this->sql->query($query_str);
    	if (!$res) {
    		//echo "[get_userscount] Не удалось выполнить запрос \"{$query_str}\"<br/>Код: ".$this->sql->errno." ".$this->sql->error;
    		return FALSE;
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
    		//echo "[get_userinfo] Не удалось выполнить запрос \"{$query_str}\"<br/>Код: ".$this->sql->errno." ".$this->sql->error;
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
    	if (!$response) {
        //echo "get_userslist. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
        return FALSE;
      }
    	
    	if ($response->num_rows == 0) return 0;

    	$res = array();
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

  /**
   * Функция проверяет, есть ли пользователь $nick в базе.
   * Возвращаяет True если пользователь есть, 0 - если польз. отсутствует и FALSE - если произошла ошибка
   * @param $nick
   * @return bool|0
   */
  public function is_user ($nick) {
    	$response = $this->sql->query("SELECT * FROM squidusers WHERE nick='$nick';");
    	if (!$response) {
    		//echo "is_user. Не удалось выполнить запрос (" . $this->sql->errno . ") " . $this->sql->error;
    		return FALSE;
    	}
    	
    	if ($response->num_rows == 0) {
    		return 0;
    	} else {
    		return TRUE;
    	}
    }
} //end of proxy worker
// -----------------------------------------------------------------------------------------------------------------------------------------------
class rejik_worker extends worker {
  private $sync_provider;

  // ==========================================================================================================================
  public function __construct ($db_config) {
    parent::__construct($db_config);
    $this->sql->set_charset("utf8"); //Устанавливаем кодировку соединения с БД Режика

    //Скрипты могут выполняться долгое время. Увеличиваем таймаут.
    if (PHP_VERSION_ID > 50500) {
      set_time_limit (120);
    }


    global $config;
    if ($config ['admin_log']==True) logger::init(); //Инициализируем логер
    //Logger::tmp_init();
    //Logger::stop();
    
//Включаем модуль синхронизации
//    if ($config['sync_enabled']) {
//      try {
//        $this->sync_provider = new SyncProvider();
//      } catch (exception $e) {}
//    }

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
  
    if ($response->num_rows == 0) return array();
  
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

  private function banlist_set_crc ($banlist, $crc) {
    //Устанавливает поле CRC для заданного банлиста
    if (count($crc) == 0) return false;
    $query = "UPDATE banlists SET `crc`=UNHEX('{$crc}') WHERE `name`='{$banlist}';";
    $response = $this->sql->query($query);

    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
    return true;
  }

  public function banlist_get_crc ($banlist) {
    if (count($banlist)==0) return false;

    $query = "SELECT HEX(`crc`) FROM banlists WHERE `name`='{$banlist}';";
    $response = $this->sql->query($query);

    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
    $tmp = $response->fetch_row();
    $response->close();
    
    return $tmp[0];
  }

  private function banlist_set_user_crc ($banlist, $user_crc) {
    //Устанавливает поле CRC для заданного банлиста
    if (count($user_crc) == 0) return false;
    $query = "UPDATE banlists SET `users_crc`=UNHEX('{$user_crc}') WHERE `name`='{$banlist}';";
    
    $response = $this->sql->query($query);

    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
    return true;
  }

  public function banlist_get_user_crc ($banlist) {
    if (count($banlist)==0) return false;

    $query = "SELECT HEX(`users_crc`) FROM banlists WHERE `name`='{$banlist}';";
    $response = $this->sql->query($query);

    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
    $tmp = $response->fetch_row();
    $response->close();
    
    return $tmp[0];
  }

  public function banlist_export ($banlist, $root_path){
    //Функция сохраняет все записи бан-листа в файле
    //Таким образом данные передаются в режик
    
    //Проверяем, существует ли банлист
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4); 

    //Получаем список URL по банлисту
    $urls = $this->banlist_get_urls($banlist);

    //Создаем каталог для банлиста
    $p = $root_path."{$banlist}/";
    if (!file_exists($p)) {
      if (!mkdir($p, 0, true)) throw new rejik_exception("Не могу создать каталог {$p}",111);
    }

    $hdl = fopen("{$p}/urls", "w");
    if(!$hdl) throw new rejik_exception("Не могу записать в файл {$p}/urls",112);  
    
    $counter=0;
    //Если в бан-листе нету УРЛов, то пропускаем его.
    if ($urls != 0) {
      //Построчно записываем в файл список пользователей.
      foreach ($urls as $row) {
        fwrite($hdl, $row."\r\n");
        $counter++;
      } 
    }
    fclose($hdl);

    //Проверяем контрольную сумму файла
    $file_hash = sha1_file ("{$p}/urls");
    $this->banlist_set_crc ($banlist, $file_hash);

    Logger::add (41, "Банлист [{$banlist}] экспортирован в файл. h=[{$file_hash}]", $banlist);
    return $counter;
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
  
    $name       = $this->sql->real_escape_string ($name      );
    $short_desc = $this->sql->real_escape_string ($short_desc);
    $full_desc  = $this->sql->real_escape_string ($full_desc );

    // 1. Проверяем, есть ли банлист с таким именем. Если есть - то исключение.
    if (array_search($name, $this->banlists_get())!==False) throw new rejik_exception("Banlist '{$name}' already exists",1);
  
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
      //Запрос со смещением
      $query = "SELECT id, url FROM urls WHERE `banlist`='{$banlist}' ORDER BY id DESC LIMIT {$offset}, {$length}";  
    } else {
      $query = "SELECT id, url FROM urls WHERE `banlist`='{$banlist}' ORDER BY id DESC";
    }
    
    $response = $this->sql->query($query);
    
    if (!$response) throw new mysql_exception($this->sql->error."\n".$query, $this->sql->errno);
  
    $res = array();
    if ($response->num_rows != 0) {
      while ($row = $response->fetch_row()) {
        //echo "<pre>"; print_r ($row); echo "</pre>";
        if ($raw_mode) {
          $res[] = $row;
        } else {
          $res[] = $row[1];
        }
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
    if ($dup!=0 and is_array($dup)) {
      //print_r($dup);
      throw new rejik_exception("URL уже существует в банлисте {$banlist}",5); 
    }

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
    Logger::add (21, "В банлист [{$banlist}] добавлен адрес [{$url}]", $banlist);

    //Если включена синхронизация, то добавляем URL в пул задач
    if ($this->sync_provider !== null) {
      $this->sync_provider->add_url_to_queue(1, $banlist, $url, $row['id']);
    }

    return $row['id'];
  }

  public function banlist_change_url ($banlist, $id, $new_url_name) {
    //Изменяет заданный URL в банлисте
    // 1. Проверяем, есть ли банлист в базе. Если нет - то исключение.
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4); 
  
    //Проверяем, существует ли в банлисте URL с новым именем.
    $dup = $this->find_duplicate($new_url_name, $banlist);
    if ($dup!=0 and is_array($dup)) {
      //print_r($dup);
      throw new rejik_exception("URL уже существует в банлисте {$banlist}",5); 
    }

    // 2. Проверяем, есть ли URL с заданным ID базе
    // ДОБАВИТЬ!!!
  
    $query = "UPDATE urls SET `url`='{$new_url_name}' WHERE `id`={$id};";
    $response = $this->sql->query($query);
  
    //2. Проверяем, выполнился запрос
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
  
    //3. Запись в лог
    Logger::add (23, "В банлисте [{$banlist}] изменен адрес #{$id} [{$new_url_name}]", $banlist);
    return True;
  }

  public function banlist_remove_url ($banlist, $id) {
    //Удаляет заданный URL из банлиста

    // Проверяем, есть ли банлист в базе. Если нет - то исключение.
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4); 
    
    //Получаем значение url по заданному id
    $query = "SELECT `url` FROM `urls` WHERE `banlist`='{$banlist}' AND `id`={$id};";
    $response = $this->sql->query($query);

    //Если ошибка, то бросаем исключение
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);

    //Сохраняем значение url и очищаем выборку
    $row = $response->fetch_row();
    $url = $row[0];
    $response->free_result();

    $query = "DELETE FROM `urls` WHERE `banlist`='{$banlist}' AND `id`={$id}";
    $response = $this->sql->query($query);
  
    //2. Проверяем, выполнился запрос
    if (!$response) throw new mysql_exception ($this->sql->error, $this->sql->errno);
  
    //3. Запись в лог
    Logger::add (22, "Из банлиста [{$banlist}] удален адрес #{$id}", $banlist);

    //Если включена синхронизация, то добавляем URL в пул задач
    if ($this->sync_provider !== null) {
      $this->sync_provider->add_url_to_queue(2, $banlist, $url, $id);
    }
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

    $res= array();
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
    Logger::add (11, "Добавление привилегий на [{$banlist}] пользователю [{$user}]", $user);
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
    Logger::add (12, "Удаление привилегий на [{$banlist}] у пользователя [{$user}]", $user);
  }
	
  public function users_acl_export ($banlist, $root_path){
    //Функция сохраняет всех пользователей бан-листа $banlist в файл, который затем используется режиком
    
    //Проверяем, существует ли банлист
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4); 

    //Получаем список пользователей для банлиста
    $users = $this->banlist_get_users($banlist);            

    //Определяем путь до папки с файлами, содержащими списки пользователей         
    if(!($hdl=fopen("{$root_path}/{$banlist}", "w"))) {
      throw new rejik_exception("Не могу записать в файл {$root_path}/{$banlist}/urls",112);
    }else{
      $counter=0;
      //Построчно записываем в файл список пользователей.
      if (!empty($users)) {
        foreach ($users as $row) {
          fwrite($hdl, $row."\r\n");
          $counter++;
        }
      }
      fclose($hdl);
    }

    //Проверяем контрольную сумму файла
    $file_hash = sha1_file ("{$root_path}/{$banlist}");
    $this->banlist_set_user_crc ($banlist, $file_hash);

    Logger::add (42, "Список пользователей [{$banlist}] экспортирован в файл. h=[{$file_hash}]", $banlist);
    return $counter;  
  }
  // ==========================================================================================================================
  // Функции импорта
  // ==========================================================================================================================
  public function import_from_csv($csv_file_path, $table, $fields) {
    $response = $this->sql->query("TRUNCATE TABLE {$table}");
    if (!$response) {
      throw new Exception ("Ошибка при очистке таблицы {$table}: (".$this->sql->errno.") ".$this->sql->error, $this->sql->errno);
    }

    $query_txt = "LOAD DATA INFILE '{$csv_file_path}' REPLACE INTO TABLE `{$table}` FIELDS TERMINATED BY ';' ENCLOSED BY '\"' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n'" ;
    //"(`url`, `banlist`)";

    $t="("; $max_fields = count($fields);
    for ($i = 0; $i <= $max_fields-1; $i++) {
      $t .= "`{$fields[$i]}`";
      if ($i != $max_fields-1) $t.=", ";
    }
    $t.=")";
    $query_txt.= " ".$t;

    $response = $this->sql->query($query_txt);
    if (!$response) {
      throw new Exception ("Ошибка при импорте CSV в БД: (".$this->sql->errno.") ".$this->sql->error, $this->sql->errno);
    }
    return $this->sql->affected_rows;
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

    $res=array();
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

    $res=array();
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
    }

    return $res;
  }

} //end of rejik_worker
  
/**
 * Проверяет, авторизован ли пользователь в текущей сессии
 */
function CheckSession () {
  global $config;
  
  //Стартуем сессию 
  session_name("sid");
  session_set_cookie_params (3600,"/{$config['proj_name']}/");
  session_start();
  
  //Проверяем, был ли залогинен пользователь
  if (!isset($_SESSION['auth']) || $_SESSION['auth'] == 0) {
    return False; // ... если нет, то ридеректим на страницу ввода пароля
  } else {
    return True;
  }
}

/**
 * Проверяет, выполнялась ли проверка состояния серверов
 */
function CheckServersState() {
  if (!isset($_SESSION['is_servers_checked']) || $_SESSION['is_servers_checked'] == 0) {
    return False;  // ... если нет, то ридеректим на страницу проверки
  } else {
    return True;  
  }
}

function CheckSelfTestResult () {
	global $config;
	$l_full_path = $_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/cron/lastcheck.log";
  if (!file_exists($l_full_path)) return False;

  if (!$hdl = fopen($l_full_path, "r")) return False;
  $msg = explode(" ", fgets ($hdl));
  fclose($hdl);

  return $msg;
}

function CloseSession() {
  session_destroy();
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
  if ($num == 1) {return $v1;}
  if (($num >= 2 && $num <5) || ($num == 0)) {return $v2;}
  if ($num >= 5) {return $v3;}
}

function print_pagenator($pages_count, $current_page=1, $id="pagination-demo") {
  echo "  <ul id='{$id}' class='pagination pagination-sm' style='margin: 0 0 10px 0;' data-pages-count='{$pages_count}'>\n";
  echo "    <li><a href='#'>&laquo;</a></li>\n";
  
  $tmp = ($pages_count<=10) ? $pages_count : 10;
  for ($i=1; $i<=$tmp; $i++) {
    echo "<li".(($i==$current_page) ? " class='active' " : "")."><a href='#'>{$i}</a></li>\n";  
  }

  echo "    <li><a href='#'>&raquo;</a></li>\n";
  echo "  </ul>\n";
}

function set_user_acl($user, $banlists) {
  //Функция выполняет назначение прав полюзователю
  global $config;
  $prx = new proxy_worker ($config['sams_db']);
  $rejik = new rejik_worker ($config['rejik_db']);

  //echo "<pre>"; print_r($banlists); echo "</pre>";

  //1. Проверяем, существует ли пользователь.
  if ( ($prx->is_user($user)) == 0 ) {
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