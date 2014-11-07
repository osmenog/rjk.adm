<?php
  include_once "classes/ServersList.php";
  include_once "classes/RejikServer.php";

class HealthPanel {
  private $local_server_name;       //Имя текущего сервера
  private $local_server_id;         //Ид текущего сервера
  private $servers_list;            //Список серверов (содержит обьекты типа RejikServer)
  private $master_server_id;        //Ид мастер-сервера
  
  //public $servers_status = array();

  //Конструктор
  public function __construct () {
    global $config;
    
    //Инициализируем сессию
    $this->init_session();

    $local_id = isset ($config['server_id']) ? $config ['server_id'] : 0;

    //Получаем массив серверов и з конфига
    $servers_cfg = $config ['servers'];

    //Создаем обьект-итератор, содержащий список серверов
    $this->servers_list = new ServersList($servers_cfg);
    
    //Получаем имя локального сервера по его ID
    $local_name = $this->servers_list->get_server_by_id($local_id);
    //Если нельзя получить имя сервера, значит возвращаем исключение
    if ($local_name===False) throw new OutOfBoundsException("В конфиге указан не существующий ID", 1);

    $this->local_server_name = $local_name;
    $this->local_server_id   = $local_id;
  }

  public function __sleep() {
    //echo "<p>Вызван метод sleep из HP</p>";
    return array ("servers_list");
  }

  public function __wakeup() {
    echo "<p>Вызван метод wakeup из HP</p>";
  }

  private function init_session() {
    global $config;
    //Стартуем сессию 
    if (session_status() != PHP_SESSION_ACTIVE) {
      session_name("sid");
      session_set_cookie_params (3600,"/{$config['proj_name']}/");
      session_start();
    }
  }

  //Функция возвращает имя текущего сервера
  public function get_local_server_name() {
    return isset($this->local_server_name) ? $this->local_server_name : '';
  }

  //Функция проверяет доступность всех серверов
  public function check_availability () {
    //var_dump($this->servers);
    
    //Увеличиваем таймаут
    set_time_limit (120);
    //echo "<pre>\n"; print_r($this->servers_list); echo "</pre>\n";
    //Перебеираем список серверов...
    foreach ($this->servers_list as $srv) {

      //.. и на каждом пытаемся установить соединение.
      //echo "<h1>Проверяем {$srv}</h1>\n";
      
      if ($srv->connect()) {
      //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
        //Если сервер доступен, то определяем режим его работы
        // сначала проверяем, является ли он мастером:
        $r = $srv->get_slave_hosts();
        //echo "<pre>\n"; print_r($r); echo "</pre>\n";
          //Проверяем на наличие ошибок
          if ($r===False) echo "<div class='alert alert-danger'><b>Ошибка:</b> ".$srv->sql_error_message."</div>\n";
          
          //Если SHOW SLAVE HOSTS что то вернул, значит к данному серверу кто-то подключен, и он является мастером.
          if ($r!==0) {
            $srv->set_work_mode(WORK_MODE_MASTER);
          }else{
            $srv->set_work_mode(WORK_MODE_SLAVE);
          }

      } else {
        //echo "<h3>Ошибка!!! Не могу подключится к серверу {$srv}</br>{$srv->sql_error_message}</h3>";
      }
      //print_r ($this->servers_list->dbg_get_servers_state());
      //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
    }
    //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
    //echo "<pre>\n"; print_r($this->servers_list); echo "</pre>\n";
  }

  //Функция возвращает коллекцию серверов, которую можно использовать как итератор
  public function get_servers() {
    return $this->servers_list;
  }

  public function check_for_errors(RejikServer $srv) {
    //Функция проверяет статус сервера на ошибки

    //Определяем режим работы
    if ($srv->get_work_mode()==WORK_MODE_SLAVE) {
      
      //Если режим SLAVE, то выполняем SHOW SLAVE STATUS
      $stat = $srv->get_status(False);
      
      //Заполняем массив интересующими нас ключами
      $error_fields = array (
                              'Last_Errno',
                              'Last_Error',
                              'Last_IO_Errno',
                              'Last_IO_Error',
                              'Last_SQL_Errno',
                              'Last_SQL_Error',
                              'Last_IO_Error_Timestamp',
                              'Last_SQL_Error_Timestamp'
                            );

      $result = array();

      //Если по заданному выше множеству ключей содержатся непустые данные в исходном массиве...
      foreach ($error_fields as $v) {
        if (array_key_exists($v,$stat)) {
          if (!empty($stat[$v])) {
            $result[$v] = $stat[$v]; // ... сохраняем этот ключ и его значение в результырующий набор.
          }
        }
      }
      
      //var_dump($result);
      return ($result); //Возвращаем результирующий набор
    }
    
  }

  private function dbg_print_servers_state() {
    //Функция выводит на экран состояние серверов.
    $srvs = $this->servers_list->servers; //Получаем указатель на массив серверов.

    for ($i=0; $i <=2 ; $i++) { 
      echo "<div>".$srvs[$i]->sql_obj->connect_errno." ".$srvs[$i]->sql_obj->connect_error;
      echo "</div>";  
    }
    
  }

  //Функция вызывает ряд команд, и производит смену мастера.
  public function switch_master($id) {
  

    try {
      //Проверяем, существует ли такой ID
      $srv = $this->servers_list->get_server_by_id($id);

      if (!$srv) { //Такого ID нет
        throw new OutOfBoundsException("В 'switch_master' указан не существующий ID", 1);
      }

      //Проверяем доступность серверов

      $this->check_availability();
       
      if (!$srv->is_connected()) throw new LogicException("Сервер {$srv} не доступен!",1);

      //Определяем режим сервера, и если он не слейв, то прекращаем работу
      if ($srv->get_work_mode() != WORK_MODE_SLAVE) throw new LogicException("Сервер {$srv} уже работает в режиме MASTER",1);

      //Определяем оставшиеся сервера, и отправляем сапрос по смене мастера
      foreach ($this->servers_list as $s) {
        //Если сервер Доступен И не является выбранным сервером
        if ($s->is_connected() &&
            // $s->get_work_mode() == WORK_MODE_SLAVE &&
            $s->get_id() != $id
        ){
          $s->change_master("host","user","pass");
        } else {
          echo "<h1>Сервер {$s} пропущен</h1>";
          //echo $s->is_connected()." - ".$s->get_work_mode()." - ".$s->get_id();
        }
      }
    } catch (Exception $e) {
      throw $e;
    }

  }

  /**
   * Функция сохраняет результат опроса серверов в текущей сессии
   * @return boolean Возвращает True в случае успеха, или False если есть ошибка.
   */
  public function save_session() {
    //Пытаемся сериализовать обьект, вызвавший это метод
    $obj = serialize($this->servers_list);

    //Сохраняем сериализованный обьект в сессию
    $_SESSION['HealthPanel'] = $obj;

    return True;
  }
  /**
   * Восстанавливаем обьекты, сохраненные в текущей сессии
   * @return [type] [description]
   */
  public function restore_session() {
    //Если в сессии ничего нет, то ошибка....выходим
    if (!isset($_SESSION['HealthPanel'])) return False;

    //Выполняем десереализацию
    $obj = unserialize ($_SESSION['HealthPanel']);
    if ($obj===False) return False; //Если обьект не поддается десериализации, то возвращаем ошибку

    //var_dump($obj);
    //var_dump($this->servers_list);

    $this->servers_list = $obj;
    return true;

  }  
}
?>