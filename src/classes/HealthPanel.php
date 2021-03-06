<?php
  include_once "classes/ServersList.php";
  include_once "classes/RejikServer.php";

  function print_server_hint($server_name, $server_id) {
    $e = "<code><abbr title='Proxy_id={$server_id}'>{$server_name}</abbr></code>";
    return $e;
  }

/**
 * Класс осуществляет процесс управления репликацией и смены отказавшего сервера
 */
class HealthPanel {
  private $current_server;          //Обьект RejikServer, указывающий текущий сервер.
  private $local_server_name;       //Имя текущего сервера
  private $local_server_id;         //Ид текущего сервера
  public  $servers_list;            //Список серверов (содержит обьекты типа RejikServer)
  private $master_servers_ids;       //Ид мастер-сервера

  /**
   * Конструктор создает массив servers_list, содержащий обьекты RejikServer,
   * также включает в себя локальный сервер
   */
  public function __construct () {
    global $config;

    //Создаем обьект-итератор, содержащий список серверов
    $this->servers_list = new ServersList($config ['servers']);

    //Определяем параметры текущего сервера, на котором запущен этот скрипт
    $local_id = isset ($config['server_id']) ? $config ['server_id'] : 0;

    //Добавляем в список текущий сервер
    $current_server = new RejikServer($config['rejik_db'][0], $config['repl_user_name'], $config['repl_user_passwd'], $local_id);
    $this->servers_list->add_server($current_server);

    $this->local_server_id    = $local_id;
    //$this->master_servers_ids = array();
    $this->current_server = $current_server;
  }

  public function __sleep() {
    return array ("servers_list", "master_servers_ids");
  }

  public function __wakeup() {
  }

  public function get_current_server() {
    return $this->current_server;
  }

  public function get_current_id() {
    return $this->local_server_id;
  }

  public function get_current_host() {
    return $this->current_server->get_real_hostname();
  }

  private function init_session() {
    global $config;
    //Стартуем сессию
    if (!isset($_SESSION)) {
      session_name("sid");
      session_set_cookie_params (3600,"/{$config['proj_name']}/");
      session_start();
    }
  }

  //Функция проверяет доступность всех серверов, и определяет режим их работы
  public function check_availability () {
    //Перебеираем список серверов...
    foreach ($this->servers_list as $srv) {
      //.. и на каждом пытаемся установить соединение.
      if ($srv->connect()) {
        //Если сервер доступен, то определяем режим его работы
        $srv_mode = $srv->get_work_mode();
        if ($srv_mode == WORK_MODE_MASTER) $this->master_servers_ids[]=$srv->get_id();
      }
    }
  }

  //Функция возвращает коллекцию серверов, которую можно использовать как итератор
  public function get_servers() {
    return $this->servers_list;
  }

  public function get_repl_errors(RejikServer $srv) {
    //Функция проверяет статус сервера на ошибки

    //Определяем режим работы
    if ($srv->get_work_mode()==WORK_MODE_SLAVE) {
      
      //Если режим SLAVE, то выполняем SHOW SLAVE STATUS
      $stat = $srv->show_slave_status();
      if (!$stat) {return array($srv->sql_last_errno => $srv->sql_last_error);}

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

  //Функция меняет режим работы сервера. $id - становится мастером.
  public function switch_master($id) {
    $check_stages = array ("Проверка состояния текущего сервера",
                           "Проверка режима работы текущего сервера",
                           "Сброс бинарных логов",
                           "Сброс режима работы",
                           "");

    try {
      //Проверяем, существует ли сервер с такоим ID
      $srv = $this->servers_list->get_server_by_id($id);
      if (!$srv) throw new OutOfBoundsException("В 'switch_master' указан не существующий ID", 1);

      echo "<h2><b>Этап 1: </b>Проверка состояния сервера</h2>\n";
      
      //Проверяем доступность серверов
      $this->check_availability();

      //Если будущий мастер-сервер определяется как "не доступный", то прерываем процесс смены.
      if (!$srv->is_connected()) {
        throw new LogicException("Сервер {$srv} не доступен!",1);
      } else {
        echo "<h3>Сервер ".print_server_hint ($srv, $srv->get_id())." доступен<h3>";
      }

      //Определяем режим работы будущего мастер-сервера, и если он уже является мастером, то прекращаем работу
      //if ($srv->_update_work_mode() == WORK_MODE_MASTER) {
      //  throw new LogicException("Сервер {$srv} уже работает в режиме MASTER",1);
      //} else {
        echo "<h3>Сервер ".print_server_hint ($srv, $srv->get_id())." готов к смене режима работы<h3>";
      //}

      //todo На самом деле тут ничего не устанавливается :[
      echo "<h2><b>Этап 2:</b> Устанавливаем режим БД \"только для чтения\"</h2>";
      //fixme доработать

      echo "<h2><b>Этап 3:</b> <small>Инициализируем МАСТЕР-режим на ".print_server_hint ($srv, $srv->get_id())."</small></h2>\n";
      //Сброс логов репликации
      try {
        $srv->do_query("STOP SLAVE;");
        $srv->do_query("RESET MASTER;");
        $master_info = $srv->show_master_status();
        if ($master_info == 0) throw new Exception("SHOW MASTER STATUS вернул нулевое значение");
      } catch (Exception $e) {
        echo "<h3>Cброс логов репликации не выполнен!</h3>";
        throw $e;
      }

      echo "<h2><b>Этап 4:</b> Выполняем смену мастера на других серверах</h2>\n";
      //Определяем оставшиеся сервера, и отправляем сапрос на смену мастера
      foreach ($this->servers_list as $s) {
        //Так как процедуры для мастера мы выполнили ранее, то его необходимо пропустить в данном обработчике.
        if( $s->get_id() == $id ) continue;

        echo "<h3>Отправляем запрос на сервер ".print_server_hint ($s, $s->get_id())."</h3>";
        //Если сервер не доступен, то пропускаем.
        if( !$s->is_connected() ) {
          echo "<h4>Сервер не доступен</h4>";
          continue;
        }

        //Отправляем запрос на смену мастера
        try {
          echo "<h4>Останавливаем сервер</h4>";
          $s->do_query("STOP SLAVE;");
          $s->do_query("RESET SLAVE;");
          $s->do_query("RESET MASTER;");

          echo "<h4>Выполняем запрос на смену</h4>";
          $s->change_master_to($srv, $master_info['File'], $master_info['Position']);

          echo "<h4>Запускаем сервер</h4>";
          $s->do_query("START SLAVE;");
        } catch (Exception $e) {
          echo "<h3>Смена мастера не выполнена!</h3>";
          throw $e;
        }
        //echo $s->is_connected()." - ".$s->_update_work_mode()." - ".$s->get_id();
      }

      $_SESSION['master_id'] = $srv->get_id();
      $_SESSION['master_available'] = True;
      $_SESSION['master_host'] = $srv;
      $_SESSION['master_error'] = "";

      echo "<h2><b>Этап 5:</b> Запись в Id мастера в локальную RDB";
      global $config;
      //Устанавливаем коннект с локальной базой RDB
      try {
        $master_cfg = HealthPanel::get_master_config();
        $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);
        $rejik->set_db_var('master_id', $id);
        $rejik->close_db();
      } catch (Exception $e) {
        echo "<h3>Запись ID мастера в RDB не выполнена!</h3>";
        throw $e;
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

  public function get_master_server() {
    //Если нам известно более чем 1 мастер, то выводим сообщение об ошибке
    //

    $m_srv_id = $this->master_servers_ids;
  
    //Если мастер сервер не был определен ранее, то считаем, что данный сервер является мастером.
    if (count($m_srv_id) == 0) {
      $m_srv = $this->servers_list->get_server_by_id($this->local_server_id);
    } elseif (count($m_srv_id) == 1) {
      $m_srv = $this->servers_list->get_server_by_id($m_srv_id);
    }else {
      return False;
    }
    
    //Сохраняем id  мастерсервера в сессию
    $_SESSION['master_id'] = $m_srv_id;

    return $m_srv;
  }

  public function test_get_master() {
    $current_srv = $this->current_server;
    $res = $current_srv->do_query("SELECT `value` FROM `variables` WHERE `name`=`master_id`;", AS_ROW);

    if ($res->num_rows == 0) { //Если запись о мастере отсутствует
      return False;
    } else {
      return True;
    }
  }

  /**
   * Функция получает из локальной RDB значение переменной master_server и сохраняет его в переменной сессии.
   */
  public function determine_master() {
    global $config;

    $_SESSION['master_id'] = -1;
    $_SESSION['master_available'] = False;
    $_SESSION['master_host'] = "";
    $_SESSION['master_error'] = "";

    //Устанавливаем коннект с локальной базой RDB и Получаем значение переменной master_id из базы
    $vw = new rejik_worker($config['rejik_db']);
    $master_pid = $vw->get_db_var("master_id");
    $vw->close_db();

    //Если значение переменной по каким-то причинам не удается получить, или такого pid не существует,
    //то считаем, что МАСТЕР-СЕРВЕР не определен. При этом пользователю должен выводиться АЛЕРТ на главной странице.
    if ($master_pid === NULL) throw new Exception ("Невозможно определить мастер-сервер");

    $_SESSION['master_id'] = $master_pid;

    //Проверяем, есть ли мастер в списке серверов
    $master_srv = $this->servers_list->get_server_by_id($master_pid);

    if ($master_srv === FALSE) throw new Exception ("Мастер-сервер с id={$master_pid} не определен в настройках");//Если нет, то выходим.

    //Пытаемся установить соединение

    $m = $master_srv->connect();
    if ($m) {
      $_SESSION['master_available'] = True;
      $_SESSION['master_host'] = $master_srv->__toString();
    } else {
      $_SESSION['master_available'] = False;
      $_SESSION['master_error'] = $master_srv->get_connect_error();
    }
    //$master_srv->close_db();
    return TRUE;
  }

  /*public function check_master_availability() {
    global $config;
    $m_pid = isset($_SESSION['master_id']) ? $_SESSION['master_id'] : -1;

    //Проверяем, еслть ли мастер сервер в списке серверов
    $master = $this->get_servers()->get_server_by_id($m_pid);
    if (!$master) {
      //Если нет, то считаем, что мастер не доступен
      $_SESSION['master_available'] = FALSE;
      return FALSE;
    } else {
      //Если сервер есть в списке, то пробуем подключиться к нему под основной учетной записью rejik_adm
      try {
        $rjk = new rejik_worker(array($master->__toString(), $config['db_user_name'], $config['db_user_pass'], 'rejik'));
      } catch (Exception $e) {
        throw new Exception ("Невозможно проверить доступность МАСТЕР-сервера: ".$e->getMessage(),$e->getCode());
      }
      $rjk->closedb();
    }

    //$_SESSION['master_available'] = ($master_srv->is_connected()) ? TRUE : FALSE;
  }*/

  public static function get_master_config() {
    global $config;

    $m_pid       = isset($_SESSION['master_id']) ? $_SESSION['master_id'] : -1;
    $m_available = isset($_SESSION['master_available']) ? $_SESSION['master_available'] : -1;
    $m_errormsg  = isset($_SESSION['master_error']) ? $_SESSION['master_error'] : "";
    $m_host      = isset($_SESSION['master_host']) ? $_SESSION['master_host'] : "";

    if ( $m_pid === -1 ) return FALSE;
    if ( !$m_available ) return FALSE;
    if ( $m_host == "" ) return FALSE;

    $cfg = array( $m_host, $config['db_user_name'], $config['db_user_pass'], 'rejik', 'utf8');

    return $cfg;
  }
}
?>