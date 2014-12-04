<?php

//Режимы работы серверов
const WORK_MODE_SLAVE = 0;
const WORK_MODE_MASTER = 1;
const WORK_MODE_UNDEFINED = - 1;

class RejikServer
{
  private $server_id;
   //Уникальный ID сервера (задается в конфиге mysql)
  private $hostname;
   //Имя хоста, на котором работает mysql
  private $username;
   //Имя пользователя
  private $password;
   //Пароль пользователя
  private $sql_obj;
   //Обьект, взаимодействующий с mysql
  private $sql_connected = False;
   //Равен true, если установлено соединение с mysql
  private $aviable = False;
   //Равен true, если хост сервера доступен (включен и пингуется)
  private $sql_error = False;
   //Равен False, если ошибок нет. Иначе содержит ошибки, связанные с mysql (не репликация)
  private $work_mode = WORK_MODE_UNDEFINED;
   //Режим работы сервера (Мастер, Слейв или Не определено)
  
  public function __construct($host, $user = '', $passwd = '', $id = 0) {
    $this->server_id = $id;
    $this->hostname = $host;
    $this->username = $user;
    $this->password = $passwd;
  }
  
  public function __destruct() {
    if ($this->sql_obj !== null) $this->sql_obj->close();
  }
  
  public function __toString() {
    return isset($this->hostname) ? $this->hostname : '';
  }
  
  /**
   * Задаем поля, которые будут сохранены при сериализации
   * @return array [description]
   */
  public function __sleep() {
    
    //echo "<p>Вызван метод sleep из RejikServer [{$this}]</p>";
    return array('server_id', 'sql_connected', 'work_mode', 'hostname', 'username', 'password', 'sql_error');
  }
  
  public function __wakeup() {
    
    //echo "<p>Вызван метод wakeup из RejikServer [{$this}]</p>";
    
    //Если сохраненный ранее обьект был подключен к базе, то восстанавливаем связь.
    if ($this->sql_connected) {
      $this->connect();
    }
  }
  
  private function set_error_var($error, $errno = 0) {
    $this->sql_error = array('error' => $error, 'errno' => $errno);
  }
  
  public function get_error() {
    return $this->sql_error;
  }

  public function get_error_str() {
    if ($this->sql_error !== False) {
      return $this->sql_error['errno'].": ".$this->sql_error['error'];
    } else {
      return '';
    }
  }

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
      
      $this->set_error_var($this->sql_obj->connect_error, $this->sql_obj->connect_errno);
      $this->sql_connected = False;
      return False;
    } else {
      $this->sql_connected = True;
      return True;
    }
  }
  
  public function get_hostname() {
    
    //Функция возвращает имя данного сервера
    return $this->hostname;
  }
  
  public function get_id() {
    
    //Функция возвращает ID данного сервера
    return $this->server_id;
  }
  
  public function is_connected() {
    return $this->sql_connected;
  }
  
  public function get_repl_status($master_mode = False) {
    
    //Функция возвращает статус работы сервера.
    //Если это мастер, то возвращается ответ на запрос SHOW MASTER STATUS
    //Если это слейв, то возвращает SHOW SLAVE STATUS
    
    //Выполняем запрос
    if ($master_mode) {
      $res = $this->sql_obj->query("SHOW MASTER STATUS;");
    } else {
      $res = $this->sql_obj->query("SHOW SLAVE STATUS;");
    }
    
    //Если ошибка, то прерываем выполнение и возвращаем False.
    if (!$res) {
      $this->sql_error = $this->set_error_var($this->sql_obj->errno, $this->sql_obj->error);
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
      $this->sql_error = $this->set_error_var($this->sql_obj->errno, $this->sql_obj->error);
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
    //Функция определяет и возвращает режим работы текущего сервера
    
    // сначала проверяем, является ли он мастером:
    $r = $this->get_slave_hosts();
    //Проверяем на наличие ошибок
    if ($r===False) return FALSE;

    //Если SHOW SLAVE HOSTS что то вернул, значит к данному серверу кто-то подключен, и он является мастером.
    if ($r !== 0) {
      $this->work_mode = WORK_MODE_MASTER;
    } else {
      //Проверяем, что сервер является Слейвом.
      //Получаем SLAVE STATUS. Если в поле host указан адрес, то это слейв.
      $tmp = $this->get_repl_status(False);
      if (isset($tmp['Slave_IO_Running']) && $tmp['Slave_IO_Running'] != 'No') {
        $this->work_mode = WORK_MODE_SLAVE;
      } else {
        $this->work_mode = WORK_MODE_UNDEFINED;
      }
    }

    return $this->work_mode;
  }
  
  public function mode() {
    return $this->work_mode;
  }
/*  public function set_work_mode($work_mode) {
    
    //Функция устанавливает режим работы
    $this->work_mode = $work_mode;
  }*/
  
  public function change_master_to(RejikServer $master_server) {
    
    //$master_host, $master_user, $master_password) {
    //Функция переключает режим репликации на другой сервер
    
    $mysqli = $this->sql_obj;
    
    //Подготавливаем запросы
    $query = "STOP SLAVE;";
    $query.= "RESET SLAVE; RESET MASTER; ";
    $ip = gethostbyname($master_server->hostname);
    $query.= "CHANGE MASTER TO MASTER_HOST = \"{$ip}\", MASTER_USER = \"{$master_server->username}\", MASTER_PASSWORD = \"{$master_server->password}\", MASTER_AUTO_POSITION=1; ";
    $query.= "START SLAVE;";
    
    //Экранируем против всякой залупы
    //$query =  $mysqli->real_escape_string($query);
    
    echo "<pre>\n";
    print_r($query);
    echo "</pre>\n";
    
    //Выполняем мультизапрос
    if ($mysqli->multi_query($query)) {
      
      // Получаем все результаты мульти-запроса
      do {
        
        //Получаем ответ от n-ого запроса
        if ($result = $mysqli->store_result()) {
          echo "<pre>\n";
          print_r($result);
          echo "</pre>\n";
          while ($row = $result->fetch_row()) {
            echo "<pre>";
            print_r($row);
            echo "</pre>\n";
          }
          
          //$result->free();
          
        } else {
          if ($mysqli->errno) {
            echo "<pre><b>При вызове store_result произошла ошибка:</b></br>\n[" . $mysqli->errno . "] " . $mysqli->error . "</pre>";
          }
        }
      } while ($mysqli->next_result());
      var_dump($mysqli->error);
    } else {
      
      //Если вылезла ошибка, выводим на экран
      echo "<pre>\n";
      echo ("<b>master_query вернул FALSE</b>\n");
      print_r($this->dbg_get_error());
      echo "</pre>\n";
    }
  } 
  public function change_master_ex($master_host, $master_user, $master_password) {
    
    //Функция переключает режим репликации на другой сервер
    echo "<h1>master_change on " . $this . "</h1>\n";
    
    //Подготавливаем запросы
    $query = "SHOW SLAVE STATUS; ";
    $query.= "STOP SLAVE; ";
    $query.= "RESET SLAVE; ";
    $query.= "CHANGE MASTER TO MASTER_HOST = '192.168.10.251', MASTER_USER = 'repl_user', MASTER_PASSWORD = 'repl', MASTER_AUTO_POSITION=1;";
    
    $mysqli = $this->sql_obj;
    
    //Экранируем против всякой залупы
    $query = $mysqli->real_escape_string($query);
    
    if ($mysqli->multi_query($query)) {
      $result = $mysqli->store_result();
      echo "<pre>\n";
      echo "<b>Первый вызов store result:</b></br>";
      print_r($result);
      echo "\n<b>errors:</b></br>";
      print_r($this->dbg_get_error());
      echo "</pre>\n";
      
      if ($mysqli->more_results()) {
        printf("-----------------\n");
      }
      
      if ($mysqli->next_result()) {
        echo "<pre><b>next_result вернул TRUE</b></pre>";
      } else {
        echo "<pre><b>next_result вернул FALSE</b></br>";
        echo "\n";
        echo ("<b>errors:</b></br>");
        print_r($this->dbg_get_error());
        echo "</pre>\n";
      }
      
      $result = $mysqli->store_result();
      echo "<pre>\n";
      echo "<b>Первый вызов store result:</b></br>";
      print_r($result);
      echo "\n<b>errors:</b></br>";
      print_r($this->dbg_get_error());
      echo "</pre>\n";
      
      if ($mysqli->more_results()) {
        printf("-----------------\n");
      }
      
      if ($mysqli->next_result()) {
        echo "<pre><b>next_result вернул TRUE</b></pre>";
      } else {
        echo "<pre><b>next_result вернул FALSE</b></br>";
        echo "\n";
        echo ("<b>errors:</b></br>");
        print_r($this->dbg_get_error());
        echo "</pre>\n";
      }
      
      exit;
      
      // Получаем все результаты мульти-запроса
      do {
        
        //Получаем ответ от n-ого запроса
        if ($result = $mysqli->store_result()) {
          echo "<pre>\n";
          print_r($result);
          echo "</pre>\n";
          while ($row = $result->fetch_row()) {
            echo "<pre>";
            print_r($row);
            echo "</pre>\n";
          }
          
          //$result->free();
          
        } else {
          var_dump($mysqli->error);
        }
        var_dump($mysqli->error);
        var_dump($mysqli->next_result());
      } while ($mysqli->next_result());
    } else {
      echo "<pre>\n";
      echo ("<b>master_query вернул FALSE</b>");
      print_r($this->dbg_get_error());
      echo "</pre>\n";
    }
  } 
  public function getHostname() {
    return $this->hostname;
  }
  
  public function dbg_get_error() {
    if (isset($this->sql_obj)) {
      $err = array(isset($this->sql_obj->connect_errno) ? $this->sql_obj->connect_errno : '-', isset($this->sql_obj->connect_error) ? $this->sql_obj->connect_error : '-', isset($this->sql_obj->errno) ? $this->sql_obj->errno : '-', isset($this->sql_obj->error) ? $this->sql_obj->error : '-');
      return $err;
    } else {
      return "Sql_obj not set";
    }
  }

  public function reset_slave() {
    //Выполняем запрос
    $res = $this->sql_obj->query("RESET SLAVE ALL;");
    
    //Если ошибка, то прерываем выполнение и возвращаем False.
    if (!$res) {
      $this->sql_error = $this->set_error_var($this->sql_obj->errno, $this->sql_obj->error);
      return False;
    }
    
    return True;
  }
}
?>