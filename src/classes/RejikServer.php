<?php

//Режимы работы серверов
const WORK_MODE_SLAVE = 0;
const WORK_MODE_MASTER = 1;
const WORK_MODE_UNDEFINED = - 1;

class RejikServer
{
  private $server_id;             //Уникальный ID сервера (задается в конфиге mysql)
  private $hostname;              //Имя хоста, на котором работает mysql
  private $real_hostname;
  private $username;              //Имя пользователя
  private $password;              //Пароль пользователя
  private $sql_obj;               //Обьект, взаимодействующий с mysql
  private $sql_connected = False; //Равен true, если установлено соединение с mysql
  private $sql_connect_error;     //Равен False, если ошибок нет. Иначе содержит ошибки, связанные с mysql (не репликация)
  private $sql_connect_errno;
  private $work_mode;             //Режим работы сервера (Мастер, Слейв или Не определено)

  private $is_read_only;

  // -------------------------------------------------------------------------------------------------------
  // МАГИЧЕСКИЕ МЕТОДЫ
  // -------------------------------------------------------------------------------------------------------
  public function __construct($host, $user = '', $passwd = '', $id = 0) {
    $this->server_id = $id;
    $this->hostname = $host;
    $this->username = $user;
    $this->password = $passwd;
    $this->work_mode = WORK_MODE_UNDEFINED;
    $this->_update_real_hostname();
  }
  
  public function __destruct() {
  }
  
  public function __toString() {
    return isset($this->hostname) ? $this->hostname : '';
  }

  public function __sleep() {
    return array('server_id',
                 'hostname',
                 'real_hostname',
                 'username',
                 'password',
                 'sql_connected',
                 'sql_connect_error',
                 'sql_connect_errno',
                 'work_mode');
  }
  
  public function __wakeup() {
    //Если сохраненный ранее обьект был подключен к базе, то восстанавливаем связь.
    if ($this->sql_connected) {
      $this->connect();
    }
  }
  // -------------------------------------------------------------------------------------------------------

  public function close_db(){
    if ($this->sql_obj !== null && $this->sql_connected != False) $this->sql_obj->close();
  }

  /**
   * Функция возвращает последнюю ошибку при подключении к SQL серверу
   * @return array|bool Возвращает массив с ошибкой если она возникала ранее.
   *                    Либо FALSE, если ошибки не было
   */
  public function get_connect_error() {
    if (isset($this->sql_connect_error)) {
      return array($this->sql_connect_error, $this->sql_connect_errno);
    } else {
      return FALSE;
    }
  }

  /**
   * Функция устанавливает соединение с SQL сервером
   * @return bool Возвращает TRUE - если соединение было установлено, либо FALSE в случае ошибки
   */
  public function connect() {
    
    //Создаем обьект mysql
    $this->sql_obj = mysqli_init();
    
    //Настраиваем таймаут
    $this->sql_obj->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    
    //Устанавливаем соединение
    @$this->sql_obj->real_connect($this->hostname, $this->username, $this->password);
    
    //Если произошла ошибка подключения к БД
    if ($this->sql_obj->connect_errno) {
      
      //После данного присваивания, данные полученные из sql_obj->connect_error будут считаться не актуальными
      //так как в манах (http://ru2.php.net/manual/ru/mysqli.connect-error.php) написано:
      //Возвращает последнее сообщение об ошибке после вызова mysqli_connect()

      $this->sql_connect_errno = $this->sql_obj->connect_errno;
      $this->sql_connect_error = $this->sql_obj->connect_error;
      $this->sql_connected = False;
      return False;
    } else {
      $this->sql_connected = True;
      try {
        $this->_update_work_mode();
        $this->_get_read_only_mode();
      } catch (Exception $e) {
        $this->sql_connect_errno = $e->getCode();
        $this->sql_connect_error = $e->getMessage();
      }
      return True;
    }
  }

  /**
   * Функция определяет реальное имя хоста, если в настройках указано, что он "localhost"
   */
  private function _update_real_hostname() {
    if ($this->hostname == 'localhost') {
      $this->real_hostname = gethostname();
    } else {
      $this->real_hostname = $this->hostname;
    }
  }

  /**
   * Функция возвращает реальное имя хоста
   * @return string имя хоста
   */
  public function get_real_hostname() {
    return $this->real_hostname;
  }

  /**
   * Функция возвращает имя хоста, указанного в настройках подключения
   * @return string имя хоста
   */
  public function get_hostname() {
    //Функция возвращает имя данного сервера
    return $this->hostname;
  }

  /**
   * //Функция возвращает ID данного сервера
   * @return int
   */
  public function get_id() {
    return $this->server_id;
  }

  /**
   * Функция возвращает состояние, - подключен ли обьект к SQL или нет
   * @return bool
   */
  public function is_connected() {
    return isset($this->sql_connected) ? $this->sql_connected : FALSE;
  }

  /**
   * Функция определяет и возвращает режим работы текущего сервера
   * @return int
   */
  private function _update_work_mode() {
    // сначала проверяем, является ли он мастером:
    $r = $this->show_slave_hosts();
    //Проверяем на наличие ошибок
    if ($r===False) return FALSE;

    //Если SHOW SLAVE HOSTS что то вернул, значит к данному серверу кто-то подключен, и он является мастером.
    if ($r !== 0) {
      $this->work_mode = WORK_MODE_MASTER;
    } else {
      //Проверяем, что сервер является Слейвом.
      //Получаем SLAVE STATUS. Если в поле host указан адрес, то это слейв.
      $tmp = $this->show_slave_status(False);
      if (isset($tmp['Slave_IO_Running']) && $tmp['Slave_IO_Running'] != 'No') {
        $this->work_mode = WORK_MODE_SLAVE;
      } else {
        $this->work_mode = WORK_MODE_UNDEFINED;
      }
    }

    return $this->work_mode;
  }

  /**
   * Функция возвращает режим работы сервера
   * @return int
   */
  public function get_work_mode() {
    return $this->work_mode;
  }

  /**
   * @param RejikServer $master_server
   * @param $file
   * @param $position
   * @throws mysql_exception
   */
  public function change_master_to(RejikServer $master_server, $file, $position) {
    //$master_host, $master_user, $master_password) {

    //Функция переключает режим репликации на другой сервер
    $mysqli = $this->sql_obj;
    $ip = gethostbyname($master_server->get_real_hostname());
    $query = "CHANGE MASTER TO MASTER_HOST = \"{$ip}\",
                       MASTER_USER = \"{$master_server->username}\",
                       MASTER_PASSWORD = \"{$master_server->password}\",
                       MASTER_LOG_FILE = \"{$file}\",
                       MASTER_LOG_POS = {$position};";
    //MASTER_AUTO_POSITION=1;";
    echo "<pre>"; print_r ($query); echo "</pre>";

    $res = $mysqli->query($query);
    if (!$res) {
      throw new mysql_exception($this->sql_obj->error, $this->sql_obj->errno);
    }
  }

  /**
   * Функция выполняет SQL запрос на установку репликации с сервером
   * @param string $query_str Текст запроса
   * @param int $return_type  Флаг, указывающий на то, в каком формате будет предоставлен результат
   * @throws Exception
   * @throws mysql_exception
   */
  public function do_query($query_str, $return_type = AS_RAW){
    if (!$this->sql_connected) {
      throw new Exception("RejikServer object not connected...");
    }

    //$query_str = $this->sql_obj->real_escape_string ($query_str);
    $res = $this->sql_obj->query($query_str);
    //Возвращает FALSE в случае неудачи.
    //В случае успешного выполнения запросов SELECT, SHOW, DESCRIBE или EXPLAIN mysqli_query() вернет объект mysqli_result.
    //Для остальных успешных запросов mysqli_query() вернет TRUE.

    if ($res === FALSE) {
      throw new mysql_exception ($this->sql_obj->error, $this->sql_obj->errno);
    } elseif ($res === TRUE) {
      return TRUE;
    } elseif ($res instanceof mysqli_result){
      switch ($return_type){
        case AS_RAW:
          return $res;
        case AS_ROW:
          return ($res->num_rows == 0) ? 0 : $res->fetch_row();
        case AS_ASSOC_ROW:
          return ($res->num_rows == 0) ? 0 : $res->fetch_assoc();
        default:
          return $res;
      }
    } else {
      throw new Exception ("mysqli->query вернула неизвестный обьект");
    }
  }

  /**
   * @return int|void
   * @throws Exception
   * @throws mysql_exception
   */
  public function show_master_status() {
    $res = $this->do_query("SHOW MASTER STATUS;", AS_ASSOC_ROW);

    if ($res === 0) return 0;

    return $res;
  }

  /**
   * @return array|int
   * @throws Exception
   * @throws mysql_exception
   */
  public function show_slave_hosts() {

    //Функция возвращает список слейвов, подклюенных к мастеру.
    //Функция выполняется только на мастере!!!!!
    //Возвращает результат выполнения запроса на SHOW SLAVE HOSTS
    //Если произошла ошибка - возвращает False
    //Если сервер ничего не вернул, то возвращает 0

    //Выполняем запрос
    $res = $this->do_query("SHOW SLAVE HOSTS;");

    //Если запрос ничего не вернул
    if ($res->num_rows == 0) return 0;

    $result = array();
    while ($row = $res->fetch_assoc()) {
      $result[] = $row;
    }

    //Очищаем полученные данные
    $res->close();

    return $result;
  }

  /**
   * @return int|void
   * @throws Exception
   * @throws mysql_exception
   */
  public function show_slave_status() {
    $res = $this->do_query("SHOW SLAVE STATUS;", AS_ASSOC_ROW);

    if ($res === 0) return 0;

    return $res;
  }

  private function _get_read_only_mode() {
    $res = $this->do_query("SHOW VARIABLES LIKE 'read_only';", AS_ROW);
    $rom = isset($res[1]) ? (strtolower($res[1])=="on" ? TRUE : FALSE) : FALSE;
    $this->is_read_only = $rom;
    return $rom;
  }

  public function enable_read_only() {
    $res = $this->do_query("FLUSH TABLES WITH READ LOCK;", AS_ROW);

    $res = $this->do_query("SET GLOBAL read_only = ON;", AS_ROW);
  }

  public function disable_read_only() {
    $res = $this->do_query("SET GLOBAL read_only = OFF;", AS_ROW);
    $res = $this->do_query("UNLOCK TABLES;", AS_ROW);

  }

  public function is_read_only() {
    return $this->is_read_only;
  }

}
?>