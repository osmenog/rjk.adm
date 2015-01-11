<?php
include_once "classes/Logger.php";
function do_sync() {
  function display_content($sp, $users_to_copy, $users_to_remove, $conflict_users) {
    if (count($users_to_copy) == 0) {
      echo "<h4>Нет пользователей на копирование</h4>";
    }else {
      $logins_with_names = $sp->get_users_shortinfo($users_to_copy, SOURCE_SAMS);
      print_table($logins_with_names, "Пользователи на копирование", 1);
    }
    echo "<br>";
    if (count($users_to_remove) == 0) {
      echo "<h4>Нет пользователей на удаление</h4>";
    }else {
      $logins_with_names = $sp->get_users_shortinfo($users_to_remove, SOURCE_RDB);
      print_table($logins_with_names, "Пользователей на удаление", 2);
    }
    echo "<br>";
    if (count($conflict_users) == 0) {
      echo "<h4>Конфликтные пользователи отсутствуют</h4>";
    }else {
      $logins_with_names = $sp->get_users_shortinfo($conflict_users, SOURCE_RDB);
      print_table($logins_with_names, "Конфликтные пользователи", 3);
    }
  }
  function users_compare (array $a, array $b) {
    //Функция необходима для array_udiff
    return strcmp($a['login'], $b['login'] );
  }

  global $config;
  echo "<h1>SYNC!</h1>";

  try {
    $sp = new sams_sync($config['server_id']);

    //Получаем список пользователей SAMS
    $sams_users = $sp->get_sams_logins();
    //echo "<pre>"; print_r($sams_users); echo "</pre>";

    if (count($sams_users) == 0) {
      echo "<p>База данных SAMS пуста. Нечего копировать</p>\n";
      return False;
    }

    //Получаем пользователей режика, привязанных к серверу.
    //Получаем ВСЕХ пользователей REJIK DB
    $rejik_users = $sp->get_rejik_logins();
    //echo "<pre>"; print_r($rejik_users); echo "</pre>";

    //Формируем список пользователей, которых нужно перенести слева -> направо
    // (пользователи, которые были созданы в SAMS)
    //$users_to_copy   = array_diff ($sams_users, $rejik_users);
    $users_to_copy = array_udiff ($sams_users, $rejik_users, "users_compare");

    // (пользователи, которые были удалены в SAMS, но остиались в REJIKDB
    $users_to_remove = array_udiff ($rejik_users, $sams_users, "users_compare");

    //Проверяем, были ли пользователи $users_to_copy добавлены в REJIK DB ранее, но для других прокси.
    $conflict_users = $sp->check_users_for_other_pids($users_to_copy);

    //Удаляем $conflict_users из списка пользователей на копирование
    if (count($conflict_users) != 0) {
      foreach ($users_to_copy as $k => $row) {
        foreach ($conflict_users as $c_row) {
          if ($row['login'] == $c_row['login']) unset ($users_to_copy[$k]);
        }
      }
    }

    //echo "<pre>"; print_r($users_to_copy); echo "</pre>";
    //echo "<pre>"; print_r($users_to_remove); echo "</pre>";
    //echo "<pre>"; print_r($conflict_users); echo "</pre>";

    //Копируем подготовленных пользователей в REJIK DB
    $sp->copy_to_rejik($users_to_copy);

    //Подключаем конфликтных пользователей к нашему прокси
    $users_to_link = $sp->link_users_to_proxy($conflict_users);

    //Выводим на экран различную информацию о ходе синхронизации.
    display_content($sp, $users_to_copy, $users_to_remove, $users_to_link);


  } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }

  return True;
}
function print_table($printable_array, $title, $id) {
  //Функция, выводит на экран одну панельку, содержащую список пользователей $printable_array
  if (count($printable_array) == 0) return;

  echo "<div class='panel panel-default'>";

  echo "<div class='panel-heading'>";
  echo "<h4 class='panel-title'>";
  echo "<a data-toggle='collapse' href='#accordion{$id}'>{$title} <b>(".count($printable_array).")</b>:</a></h4>";
  echo "</div>"; // of panel-heading

  echo "<div id='accordion{$id}' class='panel-collapse collapse'>";
  echo "<table id='table-sync' class='table table-condensed small'>\n";

  foreach ($printable_array as $row) {
    echo "<tr>";
    echo "<td>{$row['login']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "</tr>\n";
  }
  echo "</table>\n";
  echo "</div>"; //of panel-collapse

  echo "</div>"; // of panel
}

class sams_sync {
  private $is_connected = false; //Флаг, обозначающий, что соединение с двумя базами установлено
  private $sams_conn;            //MySql соединение с SAMS
  private $rejik_conn;           //MySql соединение с Rejik
  //public  $sams_users_full = array(); //Массив со всей информацией о пользователях SAMS
  //public  $rejik_users_full = array(); //Массив с логинами пользователей REJIK DB, относящихся к данному серверу
  private $server_id;

  private $sams_data_cahced = False;
  private $rejik_data_cahced = False;
  private $sams_userdata;
  private $rejik_userdata;

  public function __construct($server_id) {
    global $config;

    Logger::init();

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

  public function __destruct() {
    $this->sams_conn->closedb();
    $this->rejik_conn->closedb();
  }

  /**
   * Функция получает список логинов пользователей SAMS
   * @return array Возвращает список логинов в виде миссива:
   * Array ( [0] => test, [1] => aep )
   * @throws Exception
   */
  public function get_sams_logins() {
    $sams_data = $this->get_sams_userdata();

    //Если в бд нет пользователей, то возвращаем 0
    if ($sams_data == 0) return array();

    //Заполняем результырующий массив логинами пользователей SAMS
    $res = array();
    foreach ($sams_data as $r) {
      $res[] = array ("login" => $r['nick']);
    }
    return $res;
  }

  /**
   * Функция получает список логинов пользователей RDB
   * @return array Возвращает список логинов в виде миссива:
   * Array ( [0] => test, [1] => aep )
   * @throws Exception
   */
  public function get_rejik_logins() {
    $rejik_data = $this->get_rejik_userdata();

    //Если в бд нет пользователей, то возвращаем 0
    if ($rejik_data == 0) return array();

    $res = array();
    //Перебираем список пользователей
    foreach ($rejik_data as $row) {
      //Если пользовательь относится к текущему серверу, добавляем его в массив. Остальных игнорируем
      if ($row['proxy_id'] == $this->server_id) {
        $res[]=array ("id" => $row['id'], "login" => $row['login']);
      }
    }

    return $res;
  }

  private function get_sams_userdata ($forceupdate = FALSE) {
    //Если данные были получены ранее, то возвращаем их.
    if ($forceupdate == FALSE && $this->sams_data_cahced == TRUE) {
      return $this->sams_userdata;
    }

    $sams_data = $this->sams_conn->get_userslist(FIELDS_FULL);
    if ($sams_data == 0) return 0;
    if (!$sams_data) throw new Exception("Произошла ошибка при получении пользователей SAMS");

    $this->sams_userdata = $sams_data;
    $this->sams_data_cahced = True;

    return $this->sams_userdata;
  }

  private function get_rejik_userdata($forceupdate = FALSE) {
    //Если данные были получены ранее, то возвращаем их.

    if ($forceupdate == FALSE && $this->rejik_data_cahced == TRUE) {
      return $this->rejik_userdata;
    }

    $rejik_data = $this->rejik_conn->users_get(FIELDS_FULL);
    if ($rejik_data == 0) return 0;
    if (!$rejik_data) throw new Exception("Произошла ошибка при получении пользователей REJIK DB");

    $this->rejik_userdata= $rejik_data;
    $this->rejik_data_cahced = True;

    return $this->rejik_userdata;
  }

  public function get_users_shortinfo ($users, $source = SOURCE_SAMS) {
    //Если SOURCE_SAMS, то возвращает массив:<br>
    //Array ( [ikrivoruchko] => Криворучко Ирина Сергеевна, [nvs] => Секисова Нина Викторовна )


    //Функция получает на входе список логинов,
    // на выходе возвращает логины и ФИО пользователей.

    //Если входящий массив пуст, то завершаем функцию
    if (count($users) == 0) return False;

    $res = array();
    if ($source == SOURCE_SAMS) {
      //Получаем полную инфу о пользователях SAMS
      $sams_data = $this->get_sams_userdata();

      foreach ($users as $row) {
        $v = $row['login'];
        if (array_key_exists($v, $sams_data)) {
          $res[] = array (
                    "login" => $v,
                    "name" => trim($sams_data[$v]['family'] . " " . $sams_data[$v]['name'] . " " . $sams_data[$v]['soname'])
                   );
        }
      }
    } elseif ($source == SOURCE_RDB) {
      //Получаем полную инфу о пользователях RDB
      $rejik_data = $this->get_rejik_userdata();

      foreach ($users as $row) {
        $v = $row['login'];
        foreach ($rejik_data as $k => $u) {
          if ($v == $u['login']) {
            $res[] = array (
                      "login" => $v,
                      "name"  => isset($rejik_data[$k]['name']) ? $rejik_data[$k]['name'] : "-"
                     );
          }
        }
      }
    }

    return $res;
  }

  /**
   * Функция сравнивает списки пользователей, и возвращает пользователей,
   * которые были добавлены в БД ранее и имеют отличный от текущего P_ID
   * @param array $users_to_copy Массив содержащий список логинов пользователей, которые необходимо проверить:<br>
   * Array ( [0] => test, [1] => aep, [6] => asd, [7] => xcv )
   * @return array Массив содержащий список логинов пользователей и их pid:<br>
   * Array ( [test] => 21, [aep] => 22 )
   * @throws Exception
   */
  public function check_users_for_other_pids ($users_to_copy) {

    if (count($users_to_copy) == 0) return array();

    //Берем данные из кеша, или получаем данные о пользователях заново.
    if (!$this->rejik_data_cahced) {
      $rejik_data = $this->get_rejik_userdata();
    } else {
      $rejik_data = $this->rejik_userdata;
    }

    if ($rejik_data == 0) return array();

    $res = array();
    //Перебираем список пользователей на добавление ...
    foreach ($users_to_copy as $v) {
      // ... и проверяем, был ли пользователь добавлен на текущий сервер ранее.
      foreach ($rejik_data as $row) {
        if (($row['login'] == $v['login']) && ($row['proxy_id'] != $this->server_id))  $res[] = array("id" => $row['id'], "login" => $v['login'], "proxy_id" => $row['proxy_id']);
      }
    }

    return $res;
  }

  /**
   * Функция "подключает" пользователей к текущему прокси
   * @param $users Массив с логинами пользователей и их pid
   */
  public function link_users_to_proxy($users) {
    //Выходим, если массив пустой
    if (count($users)==0) return array();

    $linked_users = array(); //Результирующий массив

    //Обрабвтываем каждого конфликтного пользователя
    foreach ($users as $row) {
      //1.Проверяем, был ли пользователь подключен к прокси ранее.
      $uid = $row['id'];
      $pid = $row['proxy_id'];
      $local_pid = $this->server_id;

      //Проверяем, был ли пользователь подключен ранее...
      $query = "SELECT `id`, `user_id`, `assign_pid` FROM `users_linked` WHERE user_id={$uid} AND assign_pid={$local_pid};";
      $res = $this->rejik_conn->do_query($query);
      if ($res->num_rows == 0) {
        //Если не был подключен, то подключаем пользователя:
        $query = "INSERT INTO users_linked (user_id, assign_pid) VALUES('{$uid}', {$local_pid});";
        $res = $this->rejik_conn->do_query($query);
        //fixme Придумать код для сообщения
        if ($res) Logger::add(0,"Пользователь {$row['login']} (pid={$pid}) был привязан к прокси (pid={$local_pid})","",-1,"sams_sync");
        $row['assign_pid'] = $local_pid;
        $linked_users[] = $row;

      }else {
        //Если был подключен, то не обрабатываем его.
        continue;
      };
    }

    return $linked_users;
  }

  /**
   * Функция "отключает" пользователей от текущего прокси
   * @param $users
   */
  public function unlink_users_from_proxy($users) {
    //Выходим, если массив пустой
    if (count($users)==0) return array();

    $unlinked_users = $users; //Создаем копию входящего массива с пользователями

    //Обрабвтываем каждого конфликтного пользователя
    foreach ($users as $key => $row) {
      //1.Проверяем, был ли пользователь подключен к прокси ранее.
      $uid = $row['id'];
      $pid = $row['proxy_id'];
      $local_pid = $this->server_id;

      //Проверяем, был ли пользователь подключен ранее...
      $query = "SELECT `id`, `user_id`, `assign_pid` FROM `users_linked` WHERE user_id={$uid} AND assign_pid={$local_pid};";
      $res = $this->rejik_conn->do_query($query);

      if ($res->num_rows != 0) {
        //Если был подключен, то отключаем пользователя:
        $query = "DELETE FROM `users_linked` WHERE `user_id`={$uid} AND `assign_pid`={$local_pid}";
        $res = $this->rejik_conn->do_query($query);
        //fixme Придумать код для сообщения
        if ($res) Logger::add(0,"Пользователь {$row['login']} (pid={$pid}) был отключен от прокси (pid={$local_pid})","",-1);
        $unlinked_users[$key]['result'] = 'True';
      }else {
        //Если не был подключен, то не обрабатываем его.
        $unlinked_users[$key]['result'] = 'Skip';
        continue;
      };
    }

    return $unlinked_users;
  }

  public function copy_to_rejik($users) {

    if (count($users)==0) return FALSE;

    foreach ($users as $row) {
      $v = $row['login'];
      $user_data = $this->sams_userdata[$v];
      $pid = $this->server_id;
      $name = trim($user_data['family']." ".$user_data['name']." ".$user_data['soname']);
      $login = strtolower(trim($v));
      $password     = $user_data['passwd' ];
      $sams_group   = $user_data['group'  ];
      $sams_domain  = $user_data['domain' ];
      $sams_shablon = $user_data['shablon'];
      $sams_quotes  = $user_data['quotes' ];
      $sams_enabled = $user_data['enabled'];
      $sams_ip      = $user_data['ip'     ];
      $sams_mask    = $user_data['ipmask' ];


      //$name = iconv("ISO-8859-5","CP1251", $name);
      //$name = iconv("windows-1251","UTF-8", $name);

      $query = "INSERT INTO users
                (login,
                 proxy_id,
                 name,
                 password,
                 sams_group,
                 sams_domain,
                 sams_shablon,
                 sams_quotes,
                 sams_enabled,
                 sams_ip,
                 sams_ip_mask
                )
                VALUES(
                 '{$login}',
                  {$pid},
                 '{$name}',
                 '{$password}',
                 '{$sams_group}',
                 '{$sams_domain}',
                 '{$sams_shablon}',
                 {$sams_quotes},
                 {$sams_enabled},
                 '{$sams_ip}',
                 '{$sams_mask}'
                )";
      //" ON DUPLICATE KEY UPDATE name=VALUES(name), age=VALUES(age)";
      $res = $this->rejik_conn->do_query($query);
      //fixme Присвоить код для сообщения
      if ($res) Logger::add(0,"Пользователь {$login} был скопирован из SAMS в RDB","",-1,"sams_sync");
    }

    return True;
  }

}
?>
