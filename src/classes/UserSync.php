<?php

function do_sync() {
  global $config;
  echo "<h1>SYNC!</h1>";

  try {
    $sp = new sams_sync($config['server_id']);

    //Получаем список пользователей SAMS
    $sams_users = $sp->get_sams_logins();
    //var_dump($sams_users);

    if (count($sams_users) == 0) {
      echo "<p>База данных SAMS пуста. Нечего копировать</p>\n";
      return False;
    }

    //Получаем пользователей режика, привязанных к серверу.
    //Получаем ВСЕХ пользователей REJIK DB
    $rejik_users = $sp->get_rejik_logins();

    //Формируем список пользователей, которых нужно перенести слева -> направо
    // (пользователи, которые были созданы в SAMS)
    $users_to_copy   = array_diff ($sams_users, $rejik_users);
    // (пользователи, которые были удалены в SAMS, но остиались в REJIKDB
    $users_to_remove = array_diff ($rejik_users, $sams_users);

    //echo "<pre>"; print_r($users_to_copy); echo "</pre>";
    //echo "<pre>"; print_r($users_to_remove); echo "</pre>";

    //Проверяем, были ли пользователи $users_to_copy синхронизированы с REJIK DB ранее
    $conflict_users = $sp->check_users ($users_to_copy);
    echo "<pre>"; print_r($conflict_users); echo "</pre>";

    //Удаляем из списка пользователей на копирование конфликтующих пользователей
    $users_to_copy = array_diff($users_to_copy, $conflict_users);
    //echo "<pre>"; print_r($users_to_copy); echo "</pre>";

    echo "<p>Пользователей на копирование: ".count ($users_to_copy)."</p>";
    echo "<p>Пользователей на удаление: ".count ($users_to_remove)."</p>";
    echo "<p>Конфликтных пользователей: ".count ($conflict_users)."</p>";

    //Копируем подготовленных пользователей в REJIK DB
    $sp->copy_to_rejik($users_to_copy);
  } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }

  exit;


  return True;
}

class sams_sync {
  private $is_connected = false; //Флаг, обозначающий, что соединение с двумя базами установлено
  private $sams_conn;            //MySql соединение с SAMS
  private $rejik_conn;           //MySql соединение с Rejik
  public  $sams_users_full = array(); //Массив со всей информацией о пользователях SAMS
  public  $rejik_users_full = array(); //Массив с логинами пользователей REJIK DB, относящихся к данному серверу
  private $server_id;

  private $sams_data_cahced = False;
  private $rejik_data_cahced = False;
  private $sams_userdata;
  private $rejik_userdata;

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

  public function get_sams_logins() {
    try {
      //Берем данные из кеша, или получаем данные о пользователях заного.
      if (!$this->sams_data_cahced) {
        $sams_data = $this->get_sams_userdata();
      } else {
        $sams_data = $this->sams_userdata;
      }

      //Если в бд нет пользователей, то возвращаем 0
      if ($sams_data == 0) return array();

      //Заполняем результырующий массив логинами пользователей SAMS
      $res = array();
      foreach ($sams_data as $r) {
        $res[] = $r['nick'];
      }

      return $res;
    } catch (Exception $e) {
      throw $e;
    }
  }
  public function get_rejik_logins() {
    try {
      //Берем данные из кеша, или получаем данные о пользователях заного.
      if (!$this->rejik_data_cahced) {
        $rejik_data = $this->get_rejik_userdata();
      } else {
        $rejik_data = $this->rejik_userdata;
      }

      //Если в бд нет пользователей, то возвращаем 0
      if ($rejik_data == 0) return array();

      $res = array();
      //Перебираем список пользователей
      foreach ($rejik_data as $row) {
        //Если пользовательь относится к текущему серверу, добавляем его в массив. Остальных игнорируем
        if ($row['proxy_id'] == $this->server_id) {
          $res[]=$row['login'];
        }
      }

      return $res;
    } catch (Exception $e) {
      throw $e;
    }
  }

  private function get_sams_userdata() {
    try {
      $sams_data = $this->sams_conn->get_userslist(FIELDS_FULL);
      if ($sams_data == 0) return 0;
      if (!$sams_data) throw new Exception("Произошла ошибка при получении пользователей SAMS");

      $this->sams_userdata = $sams_data;
      $this->sams_data_cahced = True;
      return $this->sams_userdata;

    } catch (Exception $e) {
      throw $e;
    }
  }

  private function get_rejik_userdata() {
    try {
      $rejik_data = $this->rejik_conn->users_get(FIELDS_FULL);
      if ($rejik_data == 0) return 0;
      if (!$rejik_data) throw new Exception("Произошла ошибка при получении пользователей REJIK DB");

      $this->rejik_userdata= $rejik_data;
      $this->rejik_data_cahced = True;

      return $this->rejik_userdata;

    } catch (Exception $e) {
      throw $e;
    }
  }

  /**
   * Получает информацию о пользователях SAMS, сохраняя ее для дальнейшего использования.
   * @return array Возвращает массив в виде:
   * array ( [0] => "aaa", [1] => "bbb", ... ,  [n] => "ccc"  )
   * @throws Exception Выбрасывает в случае, если API функция завершилась с ошибкой
   */
  public function _get_sams_logins(){
    try {
      //С помощью класса PROXY WORKER получаем список пользователей самс в ПОЛНОМ виде
      $sams_full_users = $this->sams_conn->get_userslist(FIELDS_FULL);

      //Если в БД SAMS нет ни одного пользователя, то возвращаем 0
      if ($sams_full_users == 0) return 0;
      //Если произошла другая ошибка, то вызываем исключение
      if (!$sams_full_users) throw new Exception("Произошла ошибка при получении пользователей SAMS");

      $this->sams_users_full = $sams_full_users;

      //Заполняем результырующий массив логинами пользователей SAMS
      $res = array();
      foreach ($sams_full_users as $k => $r) {
        $res[$k] = $r['nick'];
      }
    } catch (Exception $e) {
      throw $e;
    }

    return $res;
  }

  /**
   * Функция получает список ВСЕХ пользователей REJIK DB, сохраняя их для дальнейшего использования.
   * @return array Возвращает массив с логинами пользователей в виде:
   * array ( [0] => "aaa", [1] => "bbb", ... ,  [n] => "ccc"  )
   * @throws Exception
   * @throws mysql_exception
   */
  public function _get_from_rejik() {
    //Получаем ВСЕХ пользователей REJIK DB
    $rejik_full_users = $this->rejik_conn->users_get(FIELDS_FULL);

    if ($rejik_full_users == 0) return 0;
    if (!$rejik_full_users) throw new Exception("Произошла ошибка при получении пользователей REJIK DB");

    $this->rejik_users_full = $rejik_full_users;

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

  public function check_users ($users_to_copy) {
    if (count($users_to_copy) == 0) return FALSE;

    //Берем данные из кеша, или получаем данные о пользователях заного.
    if (!$this->rejik_data_cahced) {
      $rejik_data = $this->get_rejik_userdata();
    } else {
      $rejik_data = $this->rejik_userdata;
    }

    if ($rejik_data == 0) return array();

    $res = array();
    //Перебираем список пользователей
    foreach ($users_to_copy as $v) {
      foreach ($rejik_data as $row) {
        if (($row['login'] == $v) && ($row['proxy_id'] != $this->server_id))  $res[] = $v;
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
