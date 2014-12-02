<?php
function do_sync() {
  echo "<h1>SYNC!</h1>";
  $sp = new sams_sync();

  //Получаем список пользователей с левой стороны
  $sp->get_from_sams();

  //Если список пуст, то копируем пользователей с правой стороны
  //ДАННЫЙ ФУНКЦИОНАЛ БУДЕТ ДОСТУПЕН В ТОМ СЛУЧАЕ, ЕСЛИ БУДЕТ НЕОБХОДИМОСТЬ В ДВУХСТОРОННЕЙ СИНХРОНИЗАЦИИ.
  if (count($sp->sams_users) == 0) {
    echo "<p>База данных SAMS пуста. Нечего копировать</p>\n";
    //return False;
    //$sp->copy_from_rejik();
  }

  //Получаем пользователей режика, привязанных к серверу.
  $sp->get_from_rejik();

  //Формируем список пользователей, которых нужно перенести слева -> направо
  // (пользователи, которые были созданы в SAMS)


  return True;
}

class sams_sync {
  private $is_connected = false; //Флаг, обозначающий, что соединение с двумя базами установлено
  private $sams_conn;            //MySql соединение с SAMS
  private $rejik_conn;           //MySql соединение с Rejik

  public $sams_users = array(); //Массив с логинами пользователей SAMS
  public $rejik_users = array();//Массив с логинами пользователей REJIK DB, относящихся к данному серверу

  public function __construct() {
    global $config;

    //Пытаемся установить соединения с БД САМС и БД РЕЖИК
    try {
      $this->sams_conn = new proxy_worker ($config['sams_db']);
    } catch (Exception $e) {
      throw new Exception ("Не могу установить соединение с БД SAMS: ".$e->getMessage(),$e->getCode());
    }

    try {
      $this->rejik_conn = new rejik_worker ($config['rejik_db']);
    } catch (Exception $e) {
      throw new Exception ("Не могу установить соединение с REJIK DB: ".$e->getMessage(),$e->getCode());
    }
  }

  public function get_from_sams(){
    //Получаем ВСЕХ пользователей SAMS
    $sams_full_users = $this->sams_conn->get_userslist();
    if (!$sams_full_users) throw new Exception("Произошла ошибка при получении пользователей SAMS");

    foreach ($sams_full_users as $r) {
      $this->sams_users[] = $r['nick'];
    }

    return $this->sams_users;
  }

  public function get_from_rejik() {
    //Получаем пользователей REJIK DB, привязанных к данному прокси
    $rejik_full_users = $this->rejik_conn->users_get();

    $this->rejik_users = $rejik_full_users;
    return true;
  }

  public function copy_from_rejik() {
    //ЗАГЛУШКА
    return;
  }
}
?>
