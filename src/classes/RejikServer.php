<?php
const WORK_MODE_SLAVE     =  0;
const WORK_MODE_MASTER    =  1;
const WORK_MODE_UNDEFINED = -1;

class RejikServer {
  private $server_id;
  private $hostname;
  private $username;
  private $password;
  private $sql_obj;
  public $sql_error_message='';
  public $sql_error_code=0;
  private $is_connected = False;
  private $mode = -1; //Режим работы сервера.

  public function  __construct($host, $user='', $passwd='', $id=0) {
    $this->server_id = $id;
    $this->hostname  = $host;
    $this->username  = $user;
    $this->password  = $passwd;
  }
  
  public function __destruct() {
    if ($this->sql_obj !== null) $this->sql_obj->close(); 
  }
  
  public function __toString() {
    return $this->hostname;
  }
  
  public function connect() {
    //Создаем обьект mysql
    $sql = mysqli_init();
    
    //Настраиваем таймаут
    $sql->options(MYSQLI_OPT_CONNECT_TIMEOUT, 7);
    
    //Устанавливаем соединение
    @$sql->real_connect($this->hostname, $this->username, $this->password);

    //Если произошла ошибка подключения к БД
    if ($sql->connect_errno) {
      $this->sql_error_message = $sql->connect_error;
      $this->sql_error_code = $sql->connect_errno;
      $this->is_connected = False;
      return False;
    } else {
      $this->sql_obj = $sql;
      $this->is_connected = True;
      return True;
    }
  }
  
  public function get_hostname() {
    //Функция аозвращает имя данного сервера
    return $this->hostname;
  }

  public function get_id() {
    //Функция возвращает ID данного сервера
    return $this->server_id;
  }
  
  public function is_connected() {
    return $this->is_connected;
  }
  
  public function get_status($master_mode = False) {
    //Функция возвращает статус работы сервера.
    //Если это мастер, то возвращается ответ на запрос SHOW MASTER STATUS
    //Если это слейв, то возвращает SHOW SLAVE STATUS

    //Выполняем запрос
    if ($master_mode) {
      $res = $this->sql_obj->query("SHOW MASTER STATUS;");
    }else{
      $res = $this->sql_obj->query("SHOW SLAVE STATUS;");  
    }
    
    //Если ошибка, то прерываем выполнение и возвращаем False.
    if (!$res) {
      $this->sql_error_message = $this->sql->errno." ".$this->sql->error;
      return False;
    }
    
    //Если запрос ничего не вернул
    if ($res->num_rows == 0) return 0;

    $row = $res->fetch_assoc();

    //Очищаем полученные данные
    $res->close();

    return $row;

  }
  
  public function get_slave_hosts() {
    //Функция возвращает список слейвов, подклюенных к мастеру.
    //Функция выполняется только на мастере!!!!!
    //Возвращает результат выполнения запроса на SHOW SLAVE HOSTS
    //Если произошла ошибка - возвращает False
    //Если сервер ничего не вернул, то возвращает 0

    //Выполняем запрос
    $res = $this->sql_obj->query("SHOW SLAVE HOSTS;");
    //Если ошибка, то прерываем выполнение и возвращаем False.
    if (!$res) {
      $this->sql_error_message = $this->sql->errno." ".$this->sql->error;
      return False;
    }
    
    //Если запрос ничего не вернул
    if ($res->num_rows == 0) return 0;

    while ($row = $res->fetch_assoc()) {
      $result[] = $row;
    }

    //Очищаем полученные данные
    $res->close();

    return $result;
  }

  public function get_work_mode() {
    //Функция возвращает режим работы
    //0-SLAVE    1-MASTER      -1 - Еще не определен.
    return $this->mode;
  }

  public function set_work_mode($work_mode) {
    //Функция устанавливает режим работы
    $this->mode = $work_mode;
  }

  public function change_master($master_host, $master_user, $master_password) {
  //Функция переключает режим репликации на другой сервер
  echo "<h1>master_change on ".$this."</h1>\n";
  //Подготавливаем запросы
  $query  = "SHOW SLAVE STATUS; ";
  $query .= "STOP SLAVE; ";
  $query .= "RESET SLAVE; ";
  $query .= "CHANGE MASTER TO MASTER_HOST = '192.168.10.251', MASTER_USER = 'repl_user', MASTER_PASSWORD = 'repl', MASTER_AUTO_POSITION=1;";

  $mysqli = $this->sql_obj;
  //echo "<pre>\n"; print_r($this); echo "</pre>\n";
  //Экранируем против всякой залупы
  $query =  $mysqli->real_escape_string($query);
  
  echo "<pre>\n"; print_r($mysqli->connect_error); echo "</pre>\n";
  if ($mysqli->multi_query($query)) {
    // Получаем все результаты мульти-запроса
    do {
      echo "<pre>\n"; print_r($mysqli); echo "</pre>\n";
      if ($result = $mysqli->store_result()) {
        while ($row = $result->fetch_row()) {
          echo "<pre>{$row[0]}</pre>\n";
        }
        $result->free();
      } else {
        var_dump($mysqli->error);
      }
    } while ($mysqli->next_result());
  } else {
    echo "<pre>\n"; print_r($mysqli->error); echo "</pre>\n";
  }
  
  
  echo "{$query}";
  
  }
}
?>