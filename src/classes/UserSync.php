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
    //FileLogger::init('user_sync.log');
    $sp = new sams_sync($config['server_id']);
    $sp->do_sync();

  } catch (DuplicateFound $dp) {
    echo "<div class='alert alert-warning'><b>Внимание</b><br> Возникла конфликтная ситуация, которую нужно разрешить в ручном режиме:<br>{$dp->getMessage()}<br/></div>\n";
  } catch (logger_exception $le) {
    echo "<div class='alert alert-danger'><b>Ошибка логгирующей функции:</b><br>{$le->getMessage()}";
    if (!empty($le->egl)) {echo "<br><pre>"; print_r ($le->egl); echo "</pre>";}
    echo "</div>\n";
  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }

  return True;

  //------------------------------------------
//  try {
//    $sp = new sams_sync($config['server_id']);
//
//    //Получаем список пользователей SAMS
//    $sams_users = $sp->get_sams_logins();
//    //echo "<pre>"; print_r($sams_users); echo "</pre>";
//
//    if (count($sams_users) == 0) {
//      echo "<p>База данных SAMS пуста. Нечего копировать</p>\n";
//      return False;
//    }
//
//    //Получаем пользователей режика, привязанных к серверу.
//    //Получаем ВСЕХ пользователей REJIK DB
//    $rejik_users = $sp->get_rejik_logins();
//    echo "<pre>"; print_r($rejik_users); echo "</pre>";
//
//    //Формируем список пользователей, которых нужно перенести слева -> направо
//    // (пользователи, которые были созданы в SAMS)
//    //$users_to_copy   = array_diff ($sams_users, $rejik_users);
//    $users_to_copy = array_udiff ($sams_users, $rejik_users, "users_compare");
//
//    // (пользователи, которые были удалены в SAMS, но остиались в REJIKDB
//    $users_to_remove = array_udiff ($rejik_users, $sams_users, "users_compare");
//
//    //Проверяем, были ли пользователи $users_to_copy добавлены в REJIK DB ранее, но для других прокси.
//    $conflict_users = $sp->check_users_for_other_pids($users_to_copy);
//
//    //Удаляем $conflict_users из списка пользователей на копирование
//    if (count($conflict_users) != 0) {
//      foreach ($users_to_copy as $k => $row) {
//        foreach ($conflict_users as $c_row) {
//          if ($row['login'] == $c_row['login']) unset ($users_to_copy[$k]);
//        }
//      }
//    }
//
//    //echo "<pre>"; print_r($users_to_copy); echo "</pre>";
//    //echo "<pre>"; print_r($users_to_remove); echo "</pre>";
//    //echo "<pre>"; print_r($conflict_users); echo "</pre>";
//
//    //Копируем подготовленных пользователей в REJIK DB
//    $sp->copy_to_rejik($users_to_copy);
//
//    //Подключаем конфликтных пользователей к нашему прокси
//    $users_to_link = $sp->link_users_to_proxy($conflict_users);
//
//    //Обрабатываем пользователей, помеченных на удаление. Перемещаем их в специальную группу SAMS
//    //$sp->remove_users($users_to_remove);
//
//
//    //Выводим на экран различную информацию о ходе синхронизации.
//    display_content($sp, $users_to_copy, $users_to_remove, $users_to_link);
//
//
//  } catch (Exception $e) {
//      echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
//  }

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
      //Если пользователь относится к текущему серверу, добавляем его в массив. Остальных игнорируем
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

        //Создаем пользователя в САМС
        //$this->sams_conn->sams_create_user($row);

        //fixme Придумать код для сообщения
        if ($res) Logger::add(0,"Пользователь {$row['login']} (pid={$pid}) был привязан к прокси (pid={$local_pid})","",-1,"sams_sync");
        $row['assign_pid'] = $local_pid;
        $linked_users[] = $row;
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
      //$user_data = $this->sams_userdata[$v];
      $pid = $this->server_id;
      $name = trim($row['family']." ".$row['name']." ".$row['soname']);
      $login = strtolower(trim($row['nick']));
      $password     = $row['passwd' ];
      $sams_group   = $row['group'  ];
      $sams_domain  = $row['domain' ];
      $sams_shablon = $row['shablon'];
      $sams_quotes  = $row['quotes' ];
      $sams_enabled = $row['enabled'];
      $sams_ip      = $row['ip'     ];
      $sams_mask    = $row['ipmask' ];


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

  private function is_exist($login, $users_data) {
    foreach ($users_data as $k=>$row) {
      if ($row['login'] == $login) return $k;
    }
    return False;
  }

  private function is_equal ($sams_user, $rejik_user) {
    //var_dump($sams_user, $rejik_user) ;
    //Пользователи считаются разными, если отличаются:
    //1. ФИО
    //2. Пароль
    //3. Активность
    //4. Квота
    //if ($sams_user[''])
    $fullname = trim( $sams_user['family'].' '.$sams_user['name'].' '.$sams_user['soname'] );
    if ($rejik_user['name'] != $fullname) return False;
    if ($rejik_user['password'] != $sams_user['passwd']) return False;
    if ($rejik_user['sams_enabled'] != $sams_user['enabled']) return False;
    if ($rejik_user['sams_quotes'] != $sams_user['quotes']) return False;
    return true;
  }

  private function is_linked_with ($user_id, $linked_data) {
    if ($linked_data === 0) return FALSE;

    foreach ($linked_data as $linked_row) {
      if ($linked_row['user_id'] == $user_id) {
        if ($linked_row['assign_pid'] == $this->server_id) return true;
      }
    }
    return false;
  }

  public function do_sync(){

    FileLogger::add("\nSyncronization sesion started!\n");

    //Получаем данные с SAMS и RDB
    FileLogger::add("Getting SAMS userdata from pid={$this->server_id}...");
    $sams_data = $this->get_sams_userdata();
    FileLogger::add(count($sams_data)." users\n");

    //Данный блок кода ищет пользователей SAMS с одинаковыми логинами.
    $counter = array();
    foreach ($sams_data as $k => &$row) {
      if (isset($counter[$row['nick']])) {
        $counter[$row['nick']]['c']++;
        throw new DuplicateFound ("В SAMS зарегистрировано несколько пользователей с логином {$row['nick']}", $row['nick'], 0);
      } else {
        $counter[$row['nick']]['c'] = 1;
        $counter[$row['nick']]['id'] = $row['id'];
      }
    }

    if ($sams_data == 0) throw new LogicException("В БД SAMS отсутствуют пользователи");

    FileLogger::add("Getting RDB userdata... ");
    $rejik_data = $this->get_rejik_userdata();
    if ($rejik_data == 0) $rejik_data = array();
    FileLogger::add(count($rejik_data)." users\n");

    FileLogger::add("Getting linked... ");
    $linked_users = $this->rejik_conn->users_get_linked_all();
    FileLogger::add(count($linked_users)." users\n");

    $sams_users_to_copy = array();
    $sams_users_updated = array();
    $sams_users_conflicted = array();
    $sams_users_to_link = array();
    $sams_users_to_remove = array();

    FileLogger::add("Proccess SAMS users:\n");
    //Перебираем пользователей SAMS
    $c = 1; $cc = count($sams_data);
    foreach ($sams_data as $sams_row) {
      FileLogger::add(" ({$c} of {$cc}) {$sams_row['nick']}:\n");
      //Проверяем: Пользователь с логином $sams_row['nick'] Имеется в RDB?
      $rjk_user_key = $this->is_exist($sams_row['nick'], $rejik_data);
      FileLogger::add("   Search user in RDB...");
      if ($rjk_user_key !== FALSE ) {
        FileLogger::add("Found\n");
        $rejik_user = & $rejik_data[$rjk_user_key]; //ТУТ ПРОИСХОДИТ ПЕРЕДАЧА ПО ССЫЛКЕ!!!


        //Проверяем, является ли пользователь "родным" по отношению к текущему прокси.
        $rejik_user_pid = $rejik_user['proxy_id'];

        ////FileLogger::add("   SAMS user server: {$this->server_id}\n");
        ////FileLogger::add("   RDB user parent server: {$rejik_user_pid}\n");
        if ($this->server_id == $rejik_user_pid) {
          //... Если является родным, то
          FileLogger::add("   Users belong to the same server with pid={$rejik_user_pid}\n");
          //Проверяем, изменены ли данные пользователя SAMS
          FileLogger::add("   Check the SAMS user has changed...");
          if (!$this->is_equal($sams_row, $rejik_user)) {
            //Если у какого-либо пользователя SAMS поменялись данные, то добавляем его в список на изменение.
            FileLogger::add("changes found\n");
            FileLogger::add("   SAMS userdata:\n".print_r($sams_row, true));
            FileLogger::add("   RDB userdata:\n".print_r($rejik_user, true));
            FileLogger::add("   *Marked as 'sams_users_updated'\n");
            $sams_users_updated[] = $sams_row;
          } else {
            FileLogger::add("not changed\n");
          }
          $rejik_user['proc'] = '1';
          $c++;
          continue;  //переходим к солед. пользователю.

        } else {//... если пользователь не родной, то
          FileLogger::add("   Users relates to different server(sams_pid={$this->server_id}; rdb_pid={$rejik_user_pid})\n");

          //Проверяем, изменены ли данные пользователя SAMS
          FileLogger::add("   Check the SAMS user has changed...");
          if (!$this->is_equal($sams_row, $rejik_user)) {
            //Если у какого-либо пользователя SAMS поменялись данные, то добавляем его в список на изменение.
            $sams_users_conflicted[] = $sams_row;
            $rejik_user['proc'] = '1';
            $c++;
            FileLogger::add("changes found\n");
            FileLogger::add("   SAMS userdata:\n".print_r($sams_row, true));
            FileLogger::add("   RDB userdata:\n".print_r($rejik_user, true));
            FileLogger::add("   *Marked as 'sams_users_updated'\n");
            continue;
          } else {
            FileLogger::add("not changed\n");
          }

          //Пользователь есть в списке подключенных пользователей?
          FileLogger::add("   Whether the SAMS user is linked...");
          if (!$this->is_linked_with($rejik_user['id'], $linked_users)) {
            FileLogger::add("not linked..\n");
            FileLogger::add("   *Marked as 'sams_users_to_link'\n");
            $sams_users_to_link[] = $rejik_user;
          } else {
            FileLogger::add("already linked\n");
          }

        }
      } else {
        //... если пользователь SAMS не найден в RDB, то помещаем в список sams_users_to_copy (копирование из SAMS в RDB)
        FileLogger::add("Not found\n");
        $sams_users_to_copy[] = $sams_row;
        FileLogger::add("   *Marked as 'sams_users_to_copy'\n");
      }

      $rejik_user['proc'] = '1';
      $c++;
    }

    //Получаем список пользователей, которые не были затронуты.
    //По факту это пользователи, которые остались в RDB, но отсутствуют в SAMS
    FileLogger::add("\n\nDetermine deleted users in SAMS:\n");
    foreach ($rejik_data as & $row) {
      if (!isset($row['proc']) && $row['proxy_id'] == $this->server_id) {
        FileLogger::add(" {$row['nick']}");
        $sams_users_to_remove[] = $row;
      }
    }

    if (count($sams_users_to_remove)==0) {
      FileLogger::add(" Deleted users not found!\n");
    } else {
      FileLogger::add(count($sams_users_to_remove)." Marked as 'sams_users_to_remove'\n");
    }

    FileLogger::add("\nsams_users_to_copy:\n");
    FileLogger::add(print_r($sams_users_to_copy, true));
    FileLogger::add("\nsams_users_updated:\n");
    FileLogger::add(print_r($sams_users_updated, true));
    FileLogger::add("\nsams_users_conflicted:\n");
    FileLogger::add(print_r($sams_users_conflicted, true));
    FileLogger::add("\nsams_users_to_link:\n");
    FileLogger::add(print_r($sams_users_to_link, true));
    FileLogger::add("\nsams_users_to_remove:\n");
    FileLogger::add(print_r($sams_users_to_remove, true));

    echo "<h3>sams_users_to_copy</h3>";
    echo "<pre style='font-size: 8pt;'>"; print_r ($sams_users_to_copy);echo "</pre>";

    echo "<h3>sams_users_updated</h3>";
    echo "<pre style='font-size: 8pt;'>"; print_r ($sams_users_updated); echo "</pre>";

    echo "<h3>sams_users_conflicted</h3>";
    echo "<pre style='font-size: 8pt;'>"; print_r ($sams_users_conflicted); echo "</pre>";

    echo "<h3>sams_users_to_link</h3>";
    echo "<pre style='font-size: 8pt;'>"; print_r ($sams_users_to_link); echo "</pre>";

    echo "<h3>sams_users_to_remove</h3>";
    echo "<pre style='font-size: 8pt;'>"; print_r ($sams_users_to_remove); echo "</pre>";

    //Копируем подготовленных пользователей в REJIK DB
    $this->copy_to_rejik($sams_users_to_copy);

    //Подключаем пользователей к нашему прокси...
    $this->link_users_to_proxy($sams_users_to_link);
    //... и переносим пользователей SAMS в группу linked
    ////$this->fix_linked_users_group($sams_users_to_link);

    //Обновляем измененных пользователей в RDB
    $this->update_users_data($sams_users_updated);

    //Удаляем пользователей, которые были удалены с SAMS
    $this->delete_removed_users($sams_users_to_remove);


    //

    FileLogger::close();
  }

  public function fix_linked_users_group($sams_users) {
    //Если в БД SAMS находятся пользователи, которые являются подключенными к текущему серверу RDB, то
    // данная функция меняет группу, к которой относятся данные пользователи.

    //1. Получаем список групп из SAMS
    $groups = $this->sams_conn->get_groups();

    //Перебираем список подключенных пользователей
    foreach ($sams_users as $row) {

    }

  }

  public function update_users_data($updated_users) {
    //Выходим, если массив пустой
    if (count($updated_users)==0) return array();

    $updated = array(); //Результирующий массив

    //Обрабвтываем каждого конфликтного пользователя
    foreach ($updated_users as $row) {
      $login=$row['nick'];
      $name = trim($row['family']." ".$row['name']." ".$row['soname']);
      $sams_quotes = $row['quotes'];
      $sams_enabled = $row['enabled'];
      $sams_passwd = $row['passwd'];

      //Проверяем, был ли пользователь подключен ранее...
      $query = "UPDATE `users` SET
                `name`    = '{$name}'   ,
                `sams_quotes`  = '{$sams_quotes}' ,
                `sams_enabled` = '{$sams_enabled}',
                `password`  = '{$sams_passwd}'
                WHERE `login`='{$login}';";

      $res = $this->rejik_conn->do_query($query);
      //fixme добавить возможность перечисления атрибутов
      if ($res) Logger::add(0,"У пользователя {$login} были обновлены атрибуты","",-1,"sams_sync");
    }

    return $updated;
  }

  public function delete_removed_users($deleted_users) {
    //Выходим, если массив пустой
    if (count($deleted_users)==0) return array();

    $deleted = array(); //Результирующий массив

    //Обрабвтываем каждого конфликтного пользователя
    foreach ($deleted_users as $row) {
      $uid = $row['id'];
      $login = $row['login'];
      $pid = $row['proxy_id'];

      $query = "DELETE FROM `users` WHERE `id` = {$uid};";
      $res = $this->rejik_conn->do_query($query);

      if ($res) Logger::add(0,"Пользователь {$login} (id={$uid}, pid={$pid}) был удален в ходе синхронизации","",-1,"sams_sync");
    }
  }
}
?>
