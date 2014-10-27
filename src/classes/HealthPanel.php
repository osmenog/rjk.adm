<?php
class HealthPanel {
  private $local_server_name;       //Имя текущего сервера
  private $local_server_id;         //Ид текущего сервера
  private $servers_list;            //Список серверов (содержит обьекты типа RejikServer)
  private $master_server_id;        //Ид мастер-сервера
  
  public $servers_status = array();

  //Конструктор
  public function __construct () {
    global $config;

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
      echo "<h1>Проверяем {$srv}</h1>\n";
      
      if ($srv->connect()) {
      //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
        //Если сервер доступен, то определяем режим его работы
        // сначала проверяем, является ли он мастером:
        $r = $srv->get_slave_hosts();
        //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
          //Проверяем на наличие ошибок
          if ($r===False) echo "<div class='alert alert-danger'><b>Ошибка:</b> ".$srv->sql_error_message."</div>\n";
          
          //Если SHOW SLAVE HOSTS что то вернул, значит к данному серверу кто-то подключен, и он является мастером.
          if ($r!==0) {
            $srv->set_work_mode(WORK_MODE_MASTER);
          }else{
            $srv->set_work_mode(WORK_MODE_SLAVE);
          }

      } else {
        echo "<h3>Ошибка!!! Не могу подключится к серверу {$srv}</br>{$srv->sql_error_message}</h3>";
      }
      //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
    }
    //echo "<pre>\n"; print_r($srv); echo "</pre>\n";
    echo "<pre>\n"; print_r($this->servers_list); echo "</pre>\n";
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
      //Нас интересуют следующие поля:

      $result = array();
      if ($stat['Last_Errno'] != 0)  $result['Last_Errno'] = $stat['Last_Errno'];
      if ($stat['Last_Error'] != '') $result['Last_Error'] = $stat['Last_Error'];
      
      if ($stat['Last_IO_Errno'] != 0) $result['Last_IO_Errno'] = $stat['Last_IO_Errno'];
      if ($stat['Last_IO_Error'] != '') $result['Last_IO_Error'] = $stat['Last_IO_Error'];

      if ($stat['Last_SQL_Errno'] != 0) $result['Last_SQL_Errno'] = $stat['Last_SQL_Errno'];
      if ($stat['Last_SQL_Error'] != '') $result['Last_SQL_Error'] = $stat['Last_SQL_Error'];

      if ($stat['Last_IO_Error_Timestamp'] != '') $result['Last_IO_Error_Timestamp'] = $stat['Last_IO_Error_Timestamp'];
      if ($stat['Last_SQL_Error_Timestamp'] != '') $result['Last_SQL_Error_Timestamp'] = $stat['Last_SQL_Error_Timestamp'];

      return ($result);
    }
    
  }

  public function switch_master($id) {
  //Функция вызывает ряд команд, и производит смену мастера.

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
        //Если сервер Доступен И работает в режиме SLAVE И не является выбранным сервером
        if ($s->is_connected() &&
            $s->get_work_mode() == WORK_MODE_SLAVE &&
            $s->get_id() != $id
        ){
  
          $s->change_master("host","user","pass");
        }
      }
    } catch (Exception $e) {
      throw $e;
    }

  }

  
}
?>