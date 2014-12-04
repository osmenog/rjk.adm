<?php



function do_sync() {
  global $config;
  echo "<h1>SYNC!</h1>";
  $sp = new sams_sync($config['server_id']);

  //Получаем список пользователей с левой стороны в виде массива [user_index] => [login]
  $sams_users = $sp->get_from_sams();

  //Если список пуст, то копируем пользователей с правой стороны
  //ДАННЫЙ ФУНКЦИОНАЛ БУДЕТ ДОСТУПЕН В ТОМ СЛУЧАЕ, ЕСЛИ БУДЕТ НЕОБХОДИМОСТЬ В ДВУХСТОРОННЕЙ СИНХРОНИЗАЦИИ.
  if (count($sams_users) == 0) {
    echo "<p>База данных SAMS пуста. Нечего копировать</p>\n";
    return False;
    //$sp->copy_from_rejik();
  }

  //Получаем пользователей режика, привязанных к серверу.
  $rejik_users = $sp->get_from_rejik();

  //Формируем список пользователей, которых нужно перенести слева -> направо
  // (пользователи, которые были созданы в SAMS)
  $users_to_copy   = array_diff ($sams_users, $rejik_users);

  // (пользователи, которые были удалены в SAMS, но остиались в REJIKDB
  $users_to_remove = array_diff ($rejik_users, $sams_users);

  //echo "<pre>"; print_r($users_to_copy); echo "</pre>";
  //echo "<pre>"; print_r($users_to_remove); echo "</pre>";

  //Ищем конфликтующих пользователей. Тот случай, когда в REJIK DB уже есть пользователь с таким логином, но относящийся к другому прокси.
  $conflict_users = $sp->check_users ($users_to_copy);
  //echo "<pre>"; print_r($conflict_users); echo "</pre>";

  //Удаляем из списка пользователей на копирование конфликтующих пользователей
  $users_to_copy = array_diff($users_to_copy, $conflict_users);
  //echo "<pre>"; print_r($users_to_copy); echo "</pre>";

  echo "<p>Пользователей на копирование: ".count ($users_to_copy)."</p>";
  echo "<p>Пользователей на удаление: ".count ($users_to_remove)."</p>";
  echo "<p>Конфликтных пользователей: ".count ($conflict_users)."</p>";

  //Копируем подготовленных пользователей в REJIK DB
  $sp->copy_to_rejik($users_to_copy);

  return True;
}

class sams_sync {
  private $is_connected = false; //Флаг, обозначающий, что соединение с двумя базами установлено
  private $sams_conn;            //MySql соединение с SAMS
  private $rejik_conn;           //MySql соединение с Rejik

  public  $sams_users_full = array(); //Массив со всей информацией о пользователях SAMS
  public $rejik_users = array(); //Массив с логинами пользователей REJIK DB, относящихся к данному серверу

  private $server_id;

  public function __construct($server_id) {
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

    $this->is_connected = True;
    $this->server_id = $server_id;
  }

  public function get_from_sams(){
    //Получаем ВСЕХ пользователей SAMS в полном виде:


    $sams_full_users = $this->sams_conn->get_userslist();
    var_dump ($sams_full_users);

    if (!$sams_full_users) throw new Exception("Произошла ошибка при получении пользователей SAMS");
    if ($sams_full_users == 0) return FALSE;

    //$this->sams_users_full = $sams_full_users;


    $res = array();
    foreach ($sams_full_users as $k => $r) {
      $res[$k] = $r['nick'];
    }

    return $res;
  }

  public function get_from_rejik() {
    //Получаем ВСЕХ пользователей REJIK DB
    $rejik_full_users = $this->rejik_conn->users_get();
    if (!$rejik_full_users) throw new Exception("Произошла ошибка при получении пользователей REJIK DB");
    if ($rejik_full_users == 0) return FALSE;

    $res = array();
    //Перебираем список пользователей
    foreach ($rejik_full_users as $row) {
      //Если пользовательь относится к текущему серверу, добавляем его в массив. Остальных игнорируем
      if ($row['proxy_id'] == $this->server_id) {
        $res[]=$row['login'];
      }
    }

    return $res;
  }

  public function check_users ($users) {
    if (count($users) == 0) return FALSE;

    //Получаем ВСЕХ пользователей REJIK DB
    $rejik_full_users = $this->rejik_conn->users_get();

    $res = array();
    //Перебираем список пользователей
    foreach ($users as $v) {
      foreach ($rejik_full_users as $row) {
        if ($row['login'] == $v) $res[] = $v;
      }
    }

    return $res;
  }

  public function copy_from_rejik() {
    //ЗАГЛУШКА
    return;
  }

  public function copy_to_rejik($users) {
    if (count($users)==0) return FALSE;
    foreach ($users as $v) {

      $this->insert_user($v);
    }
  }

  private function insert_user ($user) {
    //Функция создает одного пользователя в REJIK DB

  }
}
?>
