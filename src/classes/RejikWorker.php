<?php
include_once "classes/Workers.php";

include_once "classes/Exceptions.php";
include_once "classes/Logger.php";
include_once "classes/Checker.php";


class rejik_worker extends worker {
  // ==========================================================================================================================
  public function __construct ($db_config) {
    //todo добавить описание phpdoc
    parent::__construct($db_config);

    global $config;
    if ($config ['admin_log']==True) logger::init(); //Инициализируем логер
  }

  // ==========================================================================================================================
  // Работа с Категориями (Бан-Листами)
  // ==========================================================================================================================
  /**
   * Возвращает массив банлистов с полной информацией о них
   * @return array|0 Массив обьектов [0] => Array ([id],[name],[short_desc],[full_desc])
   *                 Возвращает 0 если запрос ничего не вернул
   */
  public function banlists_get() {
    $response = $this->slave->get_all_assoc ("SELECT * FROM `banlists`;");
    if ($response === 0) return 0;
    return $response;
  }

  /**
   * Возвращает массив содержащий имена банлистов
   * @return array|0 Массив Array ([name1], [name2], [name3])
   *                 Возвращает 0 если запрос ничего не вернул
   */
  public function banlists_get_list() {
    $response_obj = $this->slave->get_all ("SELECT `name` FROM `banlists`;");

    if ($response_obj === 0) return array();

    $result = array();
    while ($row = $response_obj->fetch_row()) {
      $result[] = $row[0];
    }

    return $result;
  }

  /**
   * Устанавливает поле CRC для заданного банлиста
   * @param $banlist
   * @param $crc
   * @return bool
   * @throws mysql_exception
   */
  private function banlist_set_crc ($banlist, $crc) {
    if (count($crc) == 0) return false;
    $query = "UPDATE banlists SET `crc`=UNHEX('{$crc}') WHERE `name`='{$banlist}';";
    $response = $this->master->query($query);
    return true;
  }

  /**
   * Получает CRC для заданного банлиста
   * @param $banlist
   * @return mixed
   * @throws mysql_exception
   */
  public function banlist_get_crc ($banlist) {
    if (count($banlist)==0) return false;
    $query = "SELECT HEX(`crc`) FROM banlists WHERE `name`='{$banlist}';";
    $response = $this->slave->get_row($query);

    return $response[0];
  }

  /**
   * Устанавливает поле CRC для списка пользователей банлиста $banlist
   * @param $banlist
   * @param $user_crc
   * @return bool
   * @throws mysql_exception
   */
  private function banlist_set_user_crc ($banlist, $user_crc) {
    if (count($user_crc) == 0) return false;
    $query = "UPDATE banlists SET `users_crc`=UNHEX('{$user_crc}') WHERE `name`='{$banlist}';";
    $response = $this->master->query($query);
    return true;
  }

  /**
   * Получает CRC для списка пользователей банлиста $banlist
   * @param $banlist
   * @return mixed
   * @throws mysql_exception
   */
  public function banlist_get_user_crc ($banlist) {
    if (count($banlist)==0) return false;
    $query = "SELECT HEX(`users_crc`) FROM banlists WHERE `name`='{$banlist}';";
    $response = $this->slave->get_row($query);
    return $response[0];
  }

  // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  /**
   * Функция сохраняет все записи бан-листа в файле. Таким образом данные передаются в режик
   * @param $banlist
   * @param $root_path
   * @return int
   * @throws Exception
   * @throws mysql_exception
   * @throws rejik_exception
   */
  public function banlist_export ($banlist, $root_path){
    echo "<h1>Функционал banlist_export отключен</h1>";
    return FALSE;

    //Проверяем, существует ли банлист
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4);

    //Получаем список URL по банлисту
    var_dump($banlist);
    $urls = $this->banlist_get_urls($banlist,0,0,TRUE);

    return;

    //Создаем каталог для банлиста
    $p = $root_path."{$banlist}/";
    if (!file_exists($p)) {
      if (!@mkdir($p, 0, true)) {
        $e=error_get_last();
        throw new rejik_exception("Не могу создать каталог: {$e['message']}",111);
      }
    }

    $hdl = @fopen("{$p}/urls", "w");
    if(!$hdl) {
      $e=error_get_last();
      throw new rejik_exception("Не могу записать в файл: {$e['message']}",112);
    }

    $counter=0;
    //Если в бан-листе нету УРЛов, то пропускаем его.
    if ($urls !== 0) {
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
  // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

  /**
   * Создает новый банлист с заданными параметрами
   * @param $name string Системное имя банлиста (должно быть в англ. раскладке
   * @param $short_desc string Короткое описание
   * @param string $full_desc string Полное описание (не обязательно)
   * @return bool Возвращает True в случае успеха
   * @throws rejik_exception
   */
  public function banlist_create ($name, $short_desc, $full_desc='') {
    $name       = $this->master->escape_string ($name      );
    $short_desc = $this->master->escape_string ($short_desc);
    $full_desc  = $this->master->escape_string ($full_desc );

    // 1. Проверяем, есть ли банлист с таким именем. Если есть - то исключение.
    if ($this->is_banlist($name)) {
      throw new rejik_exception("Banlist '{$name}' already exists",1);
    }

//    if (array_search($name, $this->banlists_get_list()) !== 0) {
//
//
//    }

    // 2. Фильтруем XSS уязвимости
    $name = htmlspecialchars ($name);
    $short_desc = htmlspecialchars ($short_desc);
    $full_desc = htmlspecialchars ($full_desc);

    // 3. Выполняем запрос
    $query = "INSERT INTO banlists SET `name`='$name', `short_desc`='$short_desc', `full_desc`='$full_desc';";
    $response = $this->master->query($query);

    //Запись в лог
    Logger::add (3, "Banlist {$name} created");
    return True;
  }

  /**
   * Возвращает информацю по заданному бан листу
   * @param $banlist
   * @return array|0 Возвращает массив [0] => Array ([id],[name],[short_desc],[full_desc])
   *                 Возвращает 0, если список банлистов пустой
   * @throws Exception
   */
  public function banlist_info ($banlist) {
    $bl = $this->banlists_get(); //Получаем список всех банлистов
    if ($bl !== 0)  {
      foreach ($bl as $value) {
        if ($value['name']==$banlist) return $value;
      }
    }

    return 0;
  }

  /**
   * Проверяет, существует ли банлист в базе
   * @param $banlist string название бан-листа
   * @return bool Возвращает TRUE если бан-лист существует
   * @throws Exception
   */
  public function is_banlist($banlist) {
    $banlists = $this->banlists_get_list();
    if ( $banlists!==0 && array_search($banlist, $banlists)!==FALSE ) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Возвращает массив пользователей к которым применяется банлист
   * @param $banlist
   * @return array|0 Возвращает массив Array (nick1, nick2, ...)
   *                 Возвращает 0, если список пользователей пустой
   * @throws mysql_exception
   */
  public function banlist_get_users($banlist) {
    $response = $this->slave->query("SELECT DISTINCT nick FROM users_acl WHERE `banlist`='{$banlist}'");

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

  /**
   * Возвращает массив УРЛов относящихся к заданному $banlist
   * @param $banlist string имя бан-листа
   * @param int $offset
   * @param int $length
   * @return array|0 Возвращаяет массив содержащий УРЛы
   *                 Возвращает 0, если банлист не содержит УРЛы
   */
  public function banlist_get_urls($banlist, $offset=0, $length=1000) {
    $query = "SELECT `id`,`url` FROM `urls` WHERE `banlist`='{$banlist}' ORDER BY id DESC";
    if ($offset!=0 or $length!=0) $query.= " LIMIT {$offset}, {$length}";
    $query.=";";

    $response = $this->slave->query($query);
    if ($response->num_rows == 0) return 0;

    $res = array();
    while ($row = $response->fetch_row()) $res[] = $row;

    $response->close();
    return $res;
  }

  /**
   * Возвращает количество УРЛов относящихся к заданному $banlist
   * @param $banlist string название бан-листа
   * @return int количество ссылок
   */
  public function banlist_urls_count ($banlist) {
    $response = $this->slave->query("SELECT Count(*) FROM urls WHERE `banlist`='{$banlist}'");

    if ($response->num_rows == 0) return 0;
    $urls_num = $response->fetch_row();

    $response->close();
    return $urls_num[0];
  }

  /**
   * Добавляет URL в банлист
   * @param $banlist
   * @param $url
   * @return mixed
   * @throws mysql_exception
   * @throws rejik_exception
   */
  public function banlist_add_url ($banlist, $url) {
    $dup = $this->find_duplicate($url, $banlist);
    if ($dup!=0 and is_array($dup)) {
      throw new rejik_exception("URL уже существует в банлисте {$banlist}",5);
    }

    $query = "INSERT INTO `urls` SET `url`='$url', `banlist`='$banlist';";
    $response = $this->master->query($query);

    //Получаем ID созданой ссылки.
    $query = "SELECT `id` FROM `urls` WHERE `url`='$url' AND `banlist`='$banlist';";
    $response = $this->master->query($query);

    $row = $response->fetch_assoc();
    //echo $row['id'];

    //3. Запись в лог
    Logger::add (21, "В банлист [{$banlist}] добавлен адрес [{$url}]", $banlist);
    return $row['id'];
  }

  /**
   * Изменяет заданный URL в банлисте
   * @param $banlist
   * @param $id
   * @param $new_url_name
   * @return bool
   * @throws mysql_exception
   * @throws rejik_exception
   */
  public function banlist_change_url ($banlist, $id, $new_url_name) {
    //Проверяем, существует ли в банлисте URL с новым именем.
    $dup = $this->find_duplicate($new_url_name, $banlist);
    if ($dup!=0 and is_array($dup)) {
      //print_r($dup);
      throw new rejik_exception("URL уже существует в банлисте {$banlist}",5);
    }

    $query = "UPDATE urls SET `url`='{$new_url_name}' WHERE `id`={$id};";
    $response = $this->master->query($query);

    //3. Запись в лог
    Logger::add (23, "В банлисте [{$banlist}] изменен адрес #{$id} [{$new_url_name}]", $banlist);
    return True;
  }

  /**
   * Удаляет заданный URL из банлиста
   */
  public function banlist_remove_url ($banlist, $id) {
    //Получаем значение url по заданному id
    $query = "SELECT `url` FROM `urls` WHERE `banlist`='{$banlist}' AND `id`={$id};";
    $response = $this->master->query($query);

    //Сохраняем значение url и очищаем выборку
    $row = $response->fetch_row();
    $url = $row[0];
    $response->free_result();

    $query = "DELETE FROM `urls` WHERE `banlist`='{$banlist}' AND `id`={$id}";
    $response = $this->master->query($query);

    //3. Запись в лог
    Logger::add (22, "Из банлиста [{$banlist}] удален адрес #{$id}", $banlist);
  }

  /**
   * Осуществляет поиск URL по маске в заданном банлисте
   * @param $banlist
   * @param $search
   * @return array|int
   */
  public function banlist_search ($banlist, $search) {
    //todo Требует переработки и обновления под новую архитектуру
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
  /**
   * Функция возвращает массив бан-листов, доступ к которым разрешен пользоваьелю.
   * @param $nick
   * @return array
   */
  public function user_acl_get ($nick) {
    $query = "SELECT DISTINCT banlist FROM users_acl WHERE nick='{$nick}';";

    $response = $this->slave->query($query);

    if ($response->num_rows == 0) return 0;

    $res = array ();
    while ($row = $response->fetch_assoc()) {
      //$row['desc'] = empty($row["desc"]) ? '' : iconv($this->db_codepage, 'UTF-8', $row['desc']);
      $res[] = $row['banlist'];
    }

    $response->close();
    return $res;
  }

  /**
   * Функция добавляет доступ пользователю $user к банлисту $banlist
   * @param $user
   * @param $banlist
   * @throws Exception
   * @throws mysql_exception
   */
  public function user_acl_add ($user, $banlist) {
    //Проверяем, существует ли банлист
    //fixme Придумать код для исключения
    if (!($this->is_banlist($banlist))) throw new Exception ("Банлист <b>{$banlist}</b> отсутствует в базе!");

    // Фильтрация XSS
    $user = htmlspecialchars($user);
    $banlist = htmlspecialchars($banlist);

    //Фильтрация sql_inj
    $user = $this->master->escape_string($user);
    $banlist = $this->master->escape_string($banlist);

    //Готовим запрос
    $query = "INSERT INTO users_acl SET `nick`='$user', `banlist`='$banlist';";
    $response = $this->master->query($query);

    //Запись в лог
    Logger::add (11, "Добавление привилегий на [{$banlist}] пользователю [{$user}]", $user);
  }

  public function user_acl_remove ($user, $banlist) {
    //todo добавить описание phpdoc
    //Проверяем, существует ли банлист
    //fixme Придумать код для исключения
    if (!($this->is_banlist($banlist))) throw new Exception ("Банлист <b>{$banlist}</b> отсутствует в базе!");

    //Готовим запрос
    $query = "DELETE FROM users_acl WHERE `nick`='$user' AND `banlist`='$banlist';";
    $response = $this->master->query($query);

    //Запись в лог
    Logger::add (12, "Удаление привилегий на [{$banlist}] у пользователя [{$user}]", $user);
  }

  /**
   * Функция сохраняет всех пользователей бан-листа $banlist в файл, который затем используется режиком
   * @param $banlist
   * @param $root_path
   * @return int
   * @throws rejik_exception
   */
  public function users_acl_export ($banlist, $root_path){
    //Проверяем, существует ли банлист
    if (!$this->is_banlist($banlist)) throw new rejik_exception("Банлист {$banlist} отсутствует в базе",4);

    //Получаем список пользователей для банлиста
    $users = $this->banlist_get_users($banlist);

    //Определяем путь до папки с файлами, содержащими списки пользователей
    if(!($hdl=@fopen("{$root_path}/{$banlist}", "w"))) {
      $e=error_get_last();
      throw new rejik_exception("Не могу записать в файл: {$e['message']}",112);
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

  /**
   * Функция возвращает список ВСЕХ пользователей, находящихся в REJIK DB
   */
  public function users_get($verbose_mode = FIELDS_FULL, $with_pid = -1) {
    if ($verbose_mode === FIELDS_FULL) {
      $query = "SELECT * FROM `users`";
    } elseif ($verbose_mode === FIELDS_LOGINS_AND_ID) {
      $query = "SELECT `id`,`login`,`proxy_id`,`name` FROM `users`";
    } else {
      return FALSE;
    }

    if ($with_pid !== -1) {
      $query .= " WHERE `proxy_id` = {$with_pid};";
    }

    $response = $this->slave->query($query);

    //Если в результате запроса ничего не извлечено
    if ($response->num_rows == 0) return 0;

    //Построчно заполням конечный массив данными, полученными из БД
    $res=array();
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
    }

    return $res;
  }

  /**
   * Функция возвращает список пользователей, подключенных к серверу с $assigned_pid
   * @param $assigned_pid ИД сервера, к которому привязаны пользователи
   * @return array
   * @throws mysql_exception
   */
  public function users_get_linked($assigned_pid){

    $query = "SELECT
                u.id,
                u.login,
                u.proxy_id,
                ul.assign_pid AS `linked_pid`,
                u.name,
                u.password,
                u.sams_group,
                u.sams_domain,
                u.sams_shablon,
                u.sams_quotes,
                u.sams_size,
                u.sams_enabled,
                u.sams_ip,
                u.sams_ip_mask,
                u.sams_flags
              FROM `users_linked` ul
              JOIN `users` u
              ON ul.user_id = u.id
              WHERE ul.assign_pid = {$assigned_pid};";

    $response = $this->slave->query($query);

    //Если в результате запроса ничего не извлечено
    if ($response->num_rows == 0) return 0;

    //Построчно заполням конечный массив данными, полученными из БД
    $res=array();
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
    }

    return $res;
  }

  public function users_get_linked_all () {
    //Проверяем, был ли пользователь подключен ранее...
    $query = "SELECT `id`, `user_id`, `assign_pid` FROM `users_linked`;";
    $response = $this->slave->query($query);

    //Если в результате запроса ничего не извлечено
    if ($response->num_rows == 0) return 0;

    //Построчно заполням конечный массив данными, полученными из БД
    $res=array();
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
    }

    return $res;
  }

  public function is_user ($username) {
    //защищаемся
    $username = $this->slave->escape_string($username);

    $response = $this->slave->query("SELECT `id`,`login`,`proxy_id`,`name` FROM `users` WHERE `login`='{$username}'");

    if ($response->num_rows == 0) {
      return FALSE;
    } elseif ($response->num_rows == 1) {
      return TRUE;
    } else {
      throw new Exception ("В базе содержится несколько пользователей, имеющих  логин {$username}.<br>Проверьте БД");
    }
  }

  public function user_info ($username = -1, $id = -1) {
    if ($username == -1 && $id == -1) throw new LogicException("Неверно заданы параметры функции <b>user_info</b>");

    //Если username не установлен, то ищем по ID
    if ($username == -1) {
      $id = $this->slave->escape_string($id);
      $response = $this->slave->query("SELECT `id`,`login`,`proxy_id`,`name` FROM `users` WHERE `id`='{$id}'");
    } elseif ($id == -1) { //Если ID не установлен, то ищем по username
      $username = $this->slave->escape_string($username);
      $response = $this->slave->query("SELECT `id`,`login`,`proxy_id`,`name` FROM `users` WHERE `login`='{$username}'");
    }


    if ($response->num_rows == 0 && $username == -1)  throw new Exception ("Пользователь c id={$id} не найден в RDB");
    if ($response->num_rows == 0 && $id == -1)  throw new Exception ("Пользователь {$username} не найден в RDB");

    $res=$response->fetch_assoc();

    $response->close();

    return $res;
  }

  // ==========================================================================================================================
  // Функции импорта
  // ==========================================================================================================================
  public function import_from_csv($csv_file_path, $table, $fields) {
  //todo Данный функционал временно отключен
    echo "<pre>Функционал import_from_csv временно отключен</pre>";

//    $response = $this->master->query("TRUNCATE TABLE {$table}");
//    if (!$response) {
//      throw new Exception ("Ошибка при очистке таблицы {$table}: (".$this->sql->errno.") ".$this->sql->error, $this->sql->errno);
//    }
//
//    $query_txt = "LOAD DATA LOCAL INFILE '{$csv_file_path}' REPLACE INTO TABLE `{$table}` FIELDS TERMINATED BY ';' ENCLOSED BY '\"' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n'" ;
//    //"(`url`, `banlist`)";
//
//    $t="("; $max_fields = count($fields);
//    for ($i = 0; $i <= $max_fields-1; $i++) {
//      $t .= "`{$fields[$i]}`";
//      if ($i != $max_fields-1) $t.=", ";
//    }
//    $t.=")";
//    $query_txt.= " ".$t;
//
//    $response = $this->sql->query($query_txt);
//    if (!$response) {
//      throw new Exception ("Ошибка при импорте CSV в БД: (".$this->sql->errno.") ".$this->sql->error, $this->sql->errno);
//    }
//    return $this->sql->affected_rows;
//    //if ($response->num_rows == 0) return 0;
  }

  // ==========================================================================================================================
  // Дополнительные функции
  // ==========================================================================================================================
//  public function check_url ($url) {
//    //todo добавить описание phpdoc
//    /*Проверяет, применяется по отношении к данной ссылки более глобальное правило*/
//
//    $parsed_url = parse_url($url);
//    if (!$parsed_url) return -1;
//
//    $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
//    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
//    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
//
//    $n_url = $host.$port.$path;
//    echo $n_url."\n";
//
//
//    $parsed_arr = explode('.', $host);
//    if ($parsed_arr[0]=='www') unset ($parsed_arr[0]);  //Убираем www
//    array_splice($parsed_arr, 0, count($parsed_arr)-2); //Оставляем только домен 2-го уровня
//
//    $host = implode('.', $parsed_arr);
//
//    echo $host."\n";
//
//    $query = "SELECT * FROM urls WHERE `url` LIKE '{$parsed_url}%';";
//    $response = $this->sql->query($query);
//
//    if ($response->num_rows == 0) return 0; //Если дубликатов нет, то выходим
//
//    $res=array();
//    while ($row = $response->fetch_assoc()) {
//      $res[] = $row;
//      // * Пытаемся распарсить ссылку на:
//    }
//
//    print_r ($res);
//  }

  public function find_duplicate($url, $banlist='') {
    //todo добавить описание phpdoc
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
    $response = $this->slave->query($query);

    if ($response->num_rows == 0) return 0; //Если дубликатов нет, то выходим

    $res=array();
    while ($row = $response->fetch_assoc()) {
      $res[] = $row;
    }

    return $res;
  }

  public function get_db_var($var_name) {
    $var_name_safed = $this->slave->escape_string($var_name);

    $res = $this->slave->get_row ("SELECT `value` FROM `variables` WHERE `name`='{$var_name_safed}';");

    if ($res === NULL) { //Если запись о мастере отсутствует
      return NULL;
    } else {
      return $res[0];
    }
  }

  public function set_db_var($var_name, $value) {
    $var_name_safed = $this->master->escape_string($var_name);
    $var_value_safed = $this->master->escape_string($value);

    $res = $this->master->query("INSERT INTO `variables` (`name`,`value`)
                            VALUES ('{$var_name_safed}', '{$var_value_safed}')
                            ON DUPLICATE KEY UPDATE `value` = '{$var_value_safed}';");

    if ($res === 0) { //Если запись о мастере отсутствует
      return False;
    } else {
      return $res[0];
    }
  }

} //end of rejik_worker

?>
