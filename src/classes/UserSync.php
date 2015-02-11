<?php
include_once "classes/Logger.php";

class sams_sync {
  private $is_connected = false; //Флаг, обозначающий, что соединение с двумя базами установлено
  private $sams_conn;            //MySql соединение с SAMS
  private $rejik_conn;           //MySql соединение с Rejik
  private $server_id;

  private $sams_data_cahced = False;
  private $rejik_data_cahced = False;
  private $sams_userdata;
  private $rejik_userdata;

  private $sams_users_to_copy;
  private $sams_users_updated;
  private $sams_users_conflicted;
  private $sams_users_to_link;
  private $sams_users_to_remove;
  private $sams_users_to_unlink;

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
      $master_cfg = HealthPanel::get_master_config();
      $this->rejik_conn = new rejik_worker ($config['rejik_db'], $master_cfg);
    } catch (Exception $e) {
      throw new Exception ("Не могу установить соединение с REJIK DB: ".$e->getMessage(),$e->getCode());
    }

    $this->sams_users_to_copy = array();    //Пользователи, которые будут скопированы из SAMS в RDB
    $this->sams_users_updated = array();    //Пользователи SAMS, данные которых будут обновлены в RDB
    $this->sams_users_conflicted = array();
    $this->sams_users_to_link = array();    //Пользователи SAMS, которые уже есть в RDB. Их необходимо "подключить" к текущему серверу
    $this->sams_users_to_remove = array();
    $this->sams_users_to_unlink = array();

    $this->is_connected = True;
    $this->server_id = $server_id;
  }

  public function __destruct() {
    $this->sams_conn->close_db();
    //$this->rejik_conn->closedb();
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
      if ( !$this->rejik_conn->is_user_linked($uid, $local_pid) ) {
        //Если не был подключен, то подключаем пользователя:

        $insert_result = $this->rejik_conn->user_link_with($uid, $local_pid);
        //$insert_result = TRUE;

        //Создаем пользователя в САМС
        //$this->sams_conn->sams_create_user($row);

        //fixme Придумать код для сообщения
        if ($insert_result) Logger::add(0,"Пользователь {$row['login']} (pid={$pid}) был привязан к прокси (pid={$local_pid})","",-1,"sams_sync");
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
      if ( $this->rejik_conn->is_user_linked($uid, $local_pid) ) {
        //Если был подключен, то отключаем пользователя:

        $unlink_result = $this->rejik_conn->user_unlink_from ($uid, $local_pid);

        //fixme Придумать код для сообщения
        if ($unlink_result) Logger::add(0,"Пользователь {$row['login']} (pid={$pid}) был отключен от прокси (pid={$local_pid})","",-1);
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
      //$name = iconv("ISO-8859-5","CP1251", $name);
      //$name = iconv("windows-1251","UTF-8", $name);
      $login = strtolower(trim($row['nick']));

      $cu_result = $this->rejik_conn->user_create($this->server_id, $row);

      //fixme Присвоить код для сообщения
      if ($cu_result) Logger::add(0,"Пользователь {$login} был скопирован из SAMS в RDB","",-1,"sams_sync");
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

    if ($sams_data == 0) throw new LogicException("В БД SAMS отсутствуют пользователи");

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

    FileLogger::add("Getting RDB userdata... ");
    $rejik_data = $this->get_rejik_userdata();
    if ($rejik_data == 0) $rejik_data = array();
    FileLogger::add(count($rejik_data)." users\n");

    FileLogger::add("Getting linked... ");
    $linked_users = $this->rejik_conn->users_get_linked_all();
    FileLogger::add(count($linked_users)." users\n");

    FileLogger::add("Proccess SAMS users:\n");
    $c = 1; $cc = count($sams_data); // Счетчики пользователей для логгера

    //Перебираем пользователей SAMS
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
            $this->sams_users_updated[] = $sams_row;
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
            $this->sams_users_conflicted[] = $sams_row;
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
            $this->sams_users_to_link[] = $rejik_user;
          } else {
            FileLogger::add("already linked\n");
          }

        } // end of "IF"
      } else {
        //... если пользователь SAMS не найден в RDB, то помещаем в список sams_users_to_copy (копирование из SAMS в RDB)
        FileLogger::add("Not found\n");
        $this->sams_users_to_copy[] = $sams_row;
        FileLogger::add("   *Marked as 'sams_users_to_copy'\n");
      }  // end of "IF"

      $rejik_user['proc'] = '1';
      $c++;
    } // of foreach

    //Получаем список пользователей, которые не были затронуты.
    //По факту это пользователи, которые остались в RDB, но отсутствуют в SAMS
    FileLogger::add("\n\nDetermine deleted users in SAMS:\n");

    foreach ($rejik_data as & $row) {
      if ( !isset($row['proc']) ) {
        if ( $row['proxy_id'] == $this->server_id ) {
          FileLogger::add(" {$row['login']}");
          $this->sams_users_to_remove[] = $row;
          $row['proc'] = '1';
        }

        if ( $this->is_linked_with($row['id'], $linked_users) ){
          $this->sams_users_to_unlink[] = $row;
          $row['proc'] = '1';
        }
      }

    }
    if (count($this->sams_users_to_remove)==0) {
      FileLogger::add(" Deleted users not found!\n");
    } else {
      FileLogger::add(count($this->sams_users_to_remove)." Marked as 'sams_users_to_remove'\n");
    }



    FileLogger::add("\nsams_users_to_copy:\n");
    FileLogger::add(print_r($this->sams_users_to_copy, true));
    FileLogger::add("\nsams_users_updated:\n");
    FileLogger::add(print_r($this->sams_users_updated, true));
    FileLogger::add("\nsams_users_conflicted:\n");
    FileLogger::add(print_r($this->sams_users_conflicted, true));
    FileLogger::add("\nsams_users_to_link:\n");
    FileLogger::add(print_r($this->sams_users_to_link, true));
    FileLogger::add("\nsams_users_to_remove:\n");
    FileLogger::add(print_r($this->sams_users_to_remove, true));
    FileLogger::add("\nsams_users_to_unlink:\n");
    FileLogger::add(print_r($this->sams_users_to_unlink, true));

    //Копируем подготовленных пользователей в REJIK DB
    //$this->copy_to_rejik($this->sams_users_to_copy);

    //Подключаем пользователей к нашему прокси...
    //$this->link_users_to_proxy($this->sams_users_to_link);
    //... и переносим пользователей SAMS в группу linked
    //$this->fix_linked_users_group($this->sams_users_to_link);

    //Обновляем измененных пользователей в RDB
    //$this->update_users_data($this->sams_users_updated);

    //Удаляем пользователей, которые были удалены с SAMS
    //$this->delete_removed_users($this->sams_users_to_remove);

    //Отключаем пользователей, которых удалили в SAMS
    //$this->unlink_users_from_proxy($this->sams_users_to_unlink);

    FileLogger::close();
  }

  public function fix_linked_users_group($sams_users) {
    global $config;

    function is_group_exists ($groups, $group_name) {
      foreach ($groups as $g) {
        if ($g[1] === $group_name) return $g[0];
      }
      return FALSE;
    }

    //Если в БД SAMS находятся пользователи, которые являются подключенными к текущему серверу RDB, то
    // данная функция меняет группу, к которой относятся данные пользователи.

    //1. Получаем список групп из SAMS
    $groups = $this->sams_conn->get_groups();

    //2. Обрабатываем список пользователей, получаем массив из pid' ов
    $pids=array();
    foreach ($sams_users as $row) {
      if ( array_search($row['proxy_id'], $pids )===FALSE ) $pids[]= $row['proxy_id'];
    }

    //3. Проверяем, есть ли в SAMS группы для серверов с такими pid'ами
    $hp = new HealthPanel();
    $servers = $hp->get_servers();

    $gids = array();
    foreach ($pids as $p) {
      $grp_name = ( ($s = $servers->get_server_by_id($p)) !== FALSE ) ? 'linked_from_'.$s->get_real_hostname() : "";

      //При необходимости обрезаем имя группы. Это нужно сделать из-за ограничений размера полей в САМС DB
      if ( $config['cut_group_name']) $grp_name = substr($grp_name,0,25);

      //Проверяем, существует ли группа в САМС
      $gid = is_group_exists($groups, $grp_name);
      if ( $gid === FALSE ) {
        //Если нет, то создаем
        if ( ($new_gid=$this->sams_conn->create_group( $grp_name )) !== FALSE ) {
          //echo "<p>{$grp_name}-{$r} created</p>";
          $gids[$p] = array($grp_name, $new_gid);
        }
      } else { //Если существует
        $gids[$p] = array ($grp_name, $gid);
      };
    }

    //Переносим юзеров в группу
    foreach ($sams_users as $k=>$row) {
      $gid = $gids[$row['proxy_id']][1];
      $update_group_result = $this->sams_conn->update_group ($row['login'], $gid);
    }

    return $sams_users;
  }

  public function update_users_data($updated_users) {
    //Выходим, если массив пустой
    if (count($updated_users)==0) return array();

    $updated = array(); //Результирующий массив

    //Обрабвтываем каждого конфликтного пользователя
    foreach ($updated_users as $row) {
      $login=$row['nick'];

      //Проверяем, был ли пользователь подключен ранее...
      $uu_result = $this->rejik_conn->user_update_data($row);

      if ($uu_result) Logger::add(0,"У пользователя {$login} были обновлены атрибуты","",-1,"sams_sync");
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

      $du_result = $this->rejik_conn->user_delete($uid);

      if ($du_result) Logger::add(0,"Пользователь {$login} (id={$uid}, pid={$pid}) был удален в ходе синхронизации","",-1,"sams_sync");
    }
  }

  public function create_sams_users($users_to_create) {
    //Если передан пустой массив, то выходим
    if ( count($users_to_create)==0 ) return array();

    //Получаем список пользователей, зарегистрированных в SAMS
    $sams_userdata = $this->get_sams_userdata(TRUE);

    //Проверяем, существует ли в БД САМСа пользователи с такими логинами
    $conflicted = array();
    foreach ($sams_userdata as $r1) {
      foreach ($users_to_create as $k=>$r2) {
        // Если есть пользователи, чью логина совпадают, то заносим их в список
        if ( $r1['nick'] ===  $r2['login']) {
          $conflicted[] = $r1;
          unset ($users_to_create[$k]);
        }
      }
    }

    if ( count($users_to_create)==0 ) return array();

    //Создаем пользователя в САМС
    foreach ($users_to_create as $k=>$row) {
      $result = $this->sams_conn->sams_create_user($row);
      if ( $result ) {
        $users_to_create[$k]['create_result'] = TRUE;
      } else {
        $users_to_create[$k]['create_result'] = FALSE;
      }
    }

    return $users_to_create;
  }

  public function delete_sams_users ($users_to_delete) {
    //Если передан пустой массив, то выходим
    if ( count($users_to_delete)==0 ) return array();

    //Удаляем пользователя в САМС
    foreach ($users_to_delete as $k=>$row) {
      $result = $this->sams_conn->sams_delete_user($row);
      if ( $result ) {
        $users_to_delete[$k]['delete_result'] = TRUE;
      } else {
        $users_to_delete[$k]['delete_result'] = FALSE;
      }
    }

    return $users_to_delete;
  }

  // Геттеры --------------------------------------------------------
  public function getSamsUsersToCopy() {
    return $this->sams_users_to_copy;
  }
  public function getSamsUsersUpdated() {
    return $this->sams_users_updated;
  }
  public function getSamsUsersConflicted() {
    return $this->sams_users_conflicted;
  }
  public function getSamsUsersToLink() {
    return $this->sams_users_to_link;
  }
  public function getSamsUsersToRemove() {
    return $this->sams_users_to_remove;
  }
  public function getSamsUsersToUnLink() {
    return $this->sams_users_to_unlink;
  }

}
?>
