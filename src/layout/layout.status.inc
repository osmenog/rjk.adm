<?php
  include_once "config.php";
  include_once "classes/ServersList.php";
  include_once "classes/RejikServer.php";
  include_once "classes/HealthPanel.php";

  /**
   * Функция выводит СОСТОЯНИЕ РАБОТЫ СЛУЖБ ТЕКУЩЕГО СЕРВЕРА
   */
  function _print_services_status() {
    global $hp;
    echo "<h3>Состояние работы служб текущего сервера:</h3>\n";
    echo "<ul>\n";
    echo "<li><b>Служба SQUID:</b> --ЗАГЛУШКА--</li>\n";
    echo "<li><b>Служба SAMS:</b> --ЗАГЛУШКА--</li>\n";
    echo "<li><b>Сервер БД для записи:</b> ".$hp->get_master_server()."</li>\n";
    echo "</ul>\n";
  }

  /**
   * Функция выводит СОСТОЯНИЕ СИНХРОНИЗАЦИИ ПОЛЬЗОВАТЕЛЕЙ
   * @throws mysql_exception
   */
  function _print_usersync_status() {
    global $config;
    global $hp;

    echo "<div class='panel panel-default'>";

    echo "  <div class='panel-heading'>";
    echo "    <div class='row' style='line-height: 27px;'>";

    echo "      <div class='col-md-7'>";
    echo "        <h3 style='margin: 0px;'>Пользователи</h3>";
    echo "      </div>";

    echo "      <div class='col-md-5 text-right'>";
    echo "       <button class='btn btn-success btn-sm' id='sync_users_btn' onClick='location.href=\"?action=status&sync_with_sams=1\"'>Синхронизировать пользователей</button>";
    echo "      </div>";
    echo "    </div>";
    echo "  </div>";

    echo "  <div class='panel-body'>";




    try {
      //Подключаемся к RDB и получаем список пользователей
      $master_cfg = HealthPanel::get_master_config();
      $rjk = new rejik_worker ($config['rejik_db'], $master_cfg);
      $users_data = $rjk->users_get(FIELDS_LOGINS_AND_ID);

      if ($users_data !== 0) { // Если список НЕ пуст, то выводим информацию о пользователях...

        //todo Добавить возможность смотреть время синхронизации
        //echo "<p>Последняя синхронизация REJIK DB с локальным SAMS: 10.10.10 Успешно</p>";


        //Собираем информацию о серверах.
        $short_srv_info = array();
        $servers = $hp->get_servers(); //Получаем список зарегистрированных в сестеме серверов

        foreach ($users_data as $row) { //Перебираем пользователей и определяем, к какому серверу они относятся...
          $pid = $row['proxy_id'];
          $srv_obj = $servers->get_server_by_id($pid);
          $srv_name = ($srv_obj !== False) ? $srv_obj->get_real_hostname() : "Unknown server (pid={$pid})";
          $short_srv_info[$pid][0] = $srv_name;

          //... также считаем количество пользователей на различных серверах.
          if (isset($short_srv_info[$pid][1])) {
            $short_srv_info[$pid][1]++;
          } else {
            $short_srv_info[$pid][1] = 1;
          }
        }

        echo "<p>Всего зарегистрировано пользователей в REJIK DB: " . count($users_data) . "<br/>\n";
        echo "<ul>\n";
        foreach ($short_srv_info as $k => $row) {
          if ($row[1] != 0) echo "<li>{$row[0]}: {$row[1]}</li>\n";
        }

        echo "</ul>\n";
        echo "</p>\n";


        $local_pid = $hp->get_current_id();
        $internal_users_count = isset($short_srv_info[$local_pid][1]) ? $short_srv_info[$local_pid][1] : 0;

        //Получаем информацию о пользователях, подключенных к локальному серверу.
        $linked_users_data = $rjk->users_get_linked($local_pid);

        $linked_srv_info = array();
        if ($linked_users_data !== 0) {
          foreach ($linked_users_data as $row) {
            $user_pid = $row['proxy_id'];
            $srv_obj = $servers->get_server_by_id($user_pid);
            $srv_name = ($srv_obj !== False) ? $srv_obj->__toString() : "Unknown server (pid={$user_pid})";
            $linked_srv_info[$user_pid][0] = $srv_name;

            //... также считаем количество пользователей на различных серверах.
            if (isset($linked_srv_info[$user_pid][1])) {
              $linked_srv_info[$user_pid][1]++;
              $linked_srv_info[$user_pid][2][] = $row['login'];
            } else {
              $linked_srv_info[$user_pid][1] = 1;
              $linked_srv_info[$user_pid][2] = array($row['login']);
            }
          }
        }


        // Блок, отображающий активных пользователей
        echo "<p>На данном сервере активно ".( $internal_users_count+ (($linked_users_data === 0) ? 0 : count($linked_users_data)) )." пользвателей. Из них:";
        echo "<ul>";
        echo "<li><b>{$internal_users_count}</b> - внутренних пользователей</li>";
        foreach ($linked_srv_info as $k => $row) {

          if ($row[1]!=0) echo "<li><b>{$row[1]}</b> - пользователей сервера {$row[0]} <a href='?action=unlink_all_users&pid={$k}'><small><i>[отключить всех]</i></small></a></li>\n";
          //print_r($row[2]);
        }
        echo "</ul></p>\n";

        // Блок, отображающий кнопки, подключающие ВСЕХ пользователей с других серверов на которых есть пользователи.
        echo "<ul>";
        foreach ($short_srv_info as $k => $row) {
          //Выводим кнопки для серверов, на которых есть пользователи. Не выводим кнопку для локального сервера
          if ($row[1]!=0 && $k!=$local_pid) echo "<li><a href='?action=link_all_users&pid={$k}'>Подключить</a> всех ({$row[1]}) пользователей сервера {$row[0]}</li>";
        }
        echo "</ul>";

      } else {   //... если список пользователей RDB пуст, то выводим предложение о синхронизации с SAMS
        echo "<div class='alert alert-warning'>В базе Rejik DB отсутствуют пользователи.<br>";
        echo "Рекомендуется выполнить синхронизацию c локальной БД SAMS:<br><br>";
        echo "<button class='btn btn-success' onClick='location.href=\"?action=status&sync_with_sams=1\"'>Синхронизировать пользователей</button>\n";
        echo "</div>";
      }
    } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Невозможно отобразить состояние пользователей:</b><br> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
    }

    //$rjk->closedb();
    echo "  </div>"; // of panel-body
    echo "</div>"; //of panel
  }

  /**
   * Функция выводит СОСТОЯНИЕ РЕПЛИЦАЦИИ БАЗЫ ДАННЫХ
   */
  function _print_replication_state() {
  global $config;
  global $hp;
  try {
    echo "<div class='row'>\n";
    echo "<div class='col-md-8'>";
    echo "<h3>Репликация данных:</h3>\n";
    echo "</div>";
    echo "<div class='col-md-4 text-right'>";
    echo "<button class='btn btn-success btn-sm' id='servers_check_btn' onClick='location.href=\"?action=servers_check\"'>Выполнить проверку</button>  \n";
    echo "</div>";
    echo "</div>\n";

    echo "<script src='js/jquery.webui-popover.js'></script>";

    //Стартуем проверку доступности серверов
    if ($config['use_check_cache'] == False) {
      $hp->check_availability();
    }

    $servers = $hp->get_servers();

    try {
      //Определяем мастер-сервер
      $hp->determine_master();
      $m_errormsg = isset ($_SESSION['master_error']) ? $_SESSION['master_error'] : "";
    } catch (Exception $e) {
      //echo "<div class='alert alert-danger'><b>Критическая ошибка при определении мастер сервера:</b><br>{$e->getCode()} : {$e->getMessage()}</div>\n";
      $m_errormsg =  ($_SESSION['master_error'] == "") ? array($e->getMessage(), $e->getCode()) : $_SESSION['master_error'];
    }

    $m_pid = isset($_SESSION['master_id']) ? $_SESSION['master_id'] : -1;
    $m_available = isset($_SESSION['master_available']) ? $_SESSION['master_available'] : -1;

    if ($m_pid == -1) {
      echo "<div class='alert alert-warning'><b>Необходимо назначить мастер-сервер</b><br>Все данные будут доступны только для чтения</div>";
    } elseif(!$m_available) {
      echo "<div class='alert alert-danger'><b>Мастер-сервер не доступен</b><br>База данных доступна только в режиме для чтения.<br><small>Возникла ошибка: {$m_errormsg[0]}</small></div>";
    }

    $servers_info = array();
    foreach ($servers as $srv) {
      $row = array( "host"       => $srv->get_real_hostname(),
                    "is_master"  => FALSE,
                    "pid"        => $srv->get_id(),
                    "mode"       => $srv->get_work_mode(),
                    "is_conn"    => $srv->is_connected(),
                    "conn_error" => $srv->get_connect_error(),
                    "is_ro"      => $srv->is_read_only(),
                    "slave_stat" => ( ($srv->is_connected()) ? $srv->show_slave_status_ex() : 0 ),
                    "master_stat"=> ( ($srv->is_connected()) ? $srv->show_master_status() : 0 )
      );
      $servers_info[] = $row;
    }

    echo "<div class='panel panel-default'>\n";
    echo "<table id='repl_status' class='table table-condensed table-bordered'>\n";
    echo "<thead>\n";
    echo "<tr>\n
            <th width='5%' class='text-center'>*</th>
            <th width='40%'>Хост</th>\n
            <th class='text-center' width='10%'>Соединение</th>\n
            <th width='30%'>Статус репликации</th>\n
            <th width='5%'>*</th>\n
            <th width='5%'>*</th>\n
          </tr>\n";
    echo "</thead>\n";

    foreach ($servers_info as $r) {
      echo "<tr>";
      // ------------------ Звездочка ------------------
      if ($m_pid == $r['pid'] && $m_available === TRUE) {
        echo "<td class='text-center'><span class='glyphicon glyphicon-star'></span></td>";
      } elseif ($m_pid == $r['pid'] && $m_available === FALSE)  {
        echo "<td class='text-center'><span class='glyphicon glyphicon-star-empty'></span></td>";
      } else {
        echo "<td></td>";
      }

      // ----------------- Имя сервера -----------------
      echo "<td>".print_server_hint ($r['host'], $r['pid'])."</td>";

      // -------------- Статус подключения -------------
      if ($r['is_conn']) {
        if ($r['conn_error'] === FALSE) {
          echo "<td class='text-center text-success'><span class='glyphicon glyphicon-ok'></span></td>";
        } else {
          echo "<td class='text-center text-warning'>
                  <span id='err{$r['pid']}' class='glyphicon glyphicon-warning-sign'></span>Предупреждение
                  <script>   $('#err{$r['pid']}').webuiPopover({content:'<small>".htmlentities($r['conn_error'][0], ENT_QUOTES)."</small>', title: 'Предупреждение', trigger: 'hover', placement: 'right-top'});  </script>
                </td>";
        }
      } else {
        echo "<td class='text-center text-danger'>
                <div id='err{$r['pid']}' class='error-container'>
                    <span class='glyphicon glyphicon-remove'></span>
                    <span>ОШИБКА</span>
                </div>
                <script>   $('#err{$r['pid']}').webuiPopover({content:'<small>".htmlentities($r['conn_error'][0], ENT_QUOTES)."</small>', title: 'Ошибка при соединении', trigger: 'hover', placement: 'right-top'});  </script>
              </td>";
      }

      // -------------- Статус репликации -------------
      if ($r['is_conn']) {
        echo "<td>";
        if ( $m_pid == $r['pid'] ) {
          echo "<span id='master-status{$r['pid']}'>MS</span>
                <script>   $('#master-status{$r['pid']}').webuiPopover({content:'".print_master_status($r['master_stat'])."', title: 'Состояние мастера', trigger: 'hover', placement: 'right-top'});  </script>";
        }

        if ( $r['slave_stat']!=0 ) {
          echo "<span id='slave-status{$r['pid']}'>SS</span>
              <script>   $('#slave-status{$r['pid']}').webuiPopover({content:'".print_slave_status($r['slave_stat'])."', title: 'Состояние подчиненного', trigger: 'hover', placement: 'right-top', width: '400px', height: '700px'});  </script>";
        }

        echo "</td>";
      } else {
        echo "<td></td>";
      }

      // ------------- Кнопка смены мастера -----------
      echo "<td><button type='button' onClick='location.href=\"?action=status&switch_master_to={$r['pid']}\"' class='btn btn-primary btn-xs'>Сделать мастером</button></td>";

      // ------------------- Резерв -------------------
      echo "<td></td>";

      echo "</tr>\n";
    } // end of foreach
    echo "</table>\n";
    echo "</div>\n"; // of panel

  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
  }
}

  function layout_status_main () {
    try {
      global $config;

      //Фильтруем входные данные
      $id = filter_input(INPUT_GET, 'switch_master_to', FILTER_VALIDATE_INT);
      if ($id === False) throw new InvalidArgumentException("Неверно задан входящий параметр 'switch_master_to'", 1);

      //Выполняем проверку прав доступа, и в случае проблем, выводим предупреждение.
      //if ($id === NULL) {
      //  include "classes/Notice.php";
      //}

      //Инициализируем главный обьект
      global $hp;
      $hp = new HealthPanel();

      //Если пришла команда смены мастера
      if ($id !== NULL) {
        echo "<div class='page-header'>";
        echo "<h1>Переключение режима работы серверов</h1>";
        echo "</div>";

        $hp->switch_master($id);
        exit;
      }

      //Загружаем состояние обьектов

      if ($config['use_check_cache'] == True) $hp->restore_session();

      echo "<div class='page-header'>\n";
      echo "<h1>\nПанель управления</br>\n";
      echo "<small>" . $hp->get_current_host(). "</small>\n</h1>\n";
      echo "<small>Внутренний идентификатор: " . $config['server_id'] . "</small>\n";
      echo "</div>\n";

      //_print_services_status();

      _print_usersync_status();

      _print_replication_state();

    } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
    }
  }

  $sync_with_sams = filter_input(INPUT_GET, 'sync_with_sams', FILTER_VALIDATE_INT);
  if ($sync_with_sams === 1) {
    include_once 'classes/UserSync.php';
    do_sync();
  } else {
    layout_status_main();
  }

  function do_sync() {
    global $config;

    echo "<div class='page-header'>\n";
    echo "<h1>\nСостояние синхронизации</h1>\n";
    echo "</div>\n";

    try {
      //По надобности включаем файловый логгер синхронизации пользователей
      if ($config['user_sync_extended_log']) FileLogger::init('user_sync.log');

      $sp = new sams_sync($config['server_id']);
      $sp->do_sync();

      if ( $sp ) {
        echo "<div class='alert alert-success'>Синхронизация выполнена успешно</div>\n";
      }

      echo "<a href='index.php?action=status'>вернуться назад</a>";
      echo "<br/>";

      //Выводим табличку с результатами
      print_sync_results ($sp);

      echo "<br/>";
      echo "<a href='index.php?action=status'>вернуться назад</a>";

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
  }

  function print_sync_results (sams_sync $sp) {
    $labels = array ("Скопировано из SAMS в RDB",
      "Обновлено в RBD",
      "Требует вмешательства",
      "Подключено к серверу",
      "Удалены из RDB",
      "Отключено от сервера"
    );

    //todo Дополнить описания
    $descriptions = array("В эту группу входят те пользователи SAMS, <b>логины</b> которых отсутствуют в RDB",
      "В эту группу входят те пользователи, у которых изменились данные в SAMS</br>Сюда входят только <b>локальные<b> пользователи!",
      "", "", "", ""
    );

    $results = array ( $sp->getSamsUsersToCopy(),
      $sp->getSamsUsersUpdated(),
      $sp->getSamsUsersConflicted(),
      $sp->getSamsUsersToLink(),
      $sp->getSamsUsersToRemove(),
      $sp->getSamsUsersToUnLink()
    );

    $fields_sets = array (
      "copy"    => array ("id", "nick", "family", "name", "soname", "enabled"),
      "updated" => array ("id", "nick", "family", "name", "soname"),
      "conf"    => array ("id", "nick", "family", "name", "soname"),
      "other"   => array ("id", "login", "name")
    );

    $fields = array ($fields_sets["copy"],
      $fields_sets["updated"],
      $fields_sets["conf"],
      $fields_sets["other"],
      $fields_sets["other"],
      $fields_sets["other"]
    );

    echo "<script src='js/jquery.webui-popover.js'></script>";

    for ($i=0; $i<=count($labels)-1; $i++) {
      if( !empty($results[$i]) ) {
        //Выводим заголовок, и создаем для него хинт
        echo "<h3 id='label{$i}'> {$labels[$i]}: ".count( $results[$i] )." </h3>";
        echo "<script>   $('#label{$i}').webuiPopover({content:'<small>{$descriptions[$i]}</small>', trigger: 'hover', placement: 'left'});  </script>";

        //Выводим данные в виде таблицы
        //echo "<pre style='font-size: 8pt;'>"; print_r ( $results[$i] ); echo "</pre>";
        print_table ( $results[$i], $fields[$i] );
        $flag_not_empty = true;
      }
    }

    if ( empty($flag_not_empty) ) {
      echo "<div class='alert alert-warning'>Изменения не выявлены.</div>\n";
    }
  }

  function print_table ($data, $fields) {
    echo "<table id='table-sync' class='table table-condensed small'>\n";

    echo "<thead><tr>";
    for ($i=0; $i<=6; $i++) {
      echo "<th>".( isset($fields[$i]) ? $fields[$i] : "" )."</th>";
    }
    echo "</tr></thead>";

    foreach ($data as $row) {
      echo "<tr>";
      //echo "<td>".print_r($row, true)."</td>";
      foreach ($fields as $f) { echo "<td>{$row[$f]}</td>"; }
      echo "</tr>\n";
    }

    echo "</table>\n";
  }

  function print_table_old($printable_array, $title, $id) {
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

  function print_slave_status ($ss) {
    $hint="";
    $hint.="<table class='slave-status-hint table table-condensed'>";
    foreach ($ss as $k=>$v ) {
      //Тут вызывается функция, которая анализирует сравнивает значения $v параметра $k с эталонными, и возвращает параметр, отвечающий за стиль элемента (красный\зеленый\желтый фон, жирный шрифт, цвет шрифта)
      $td = "<td>".$k."</td>";
      $td.= "<td>".htmlentities($v, ENT_QUOTES)."</td>";
      $hint.="<tr>{$td}</tr>";
    }
    $hint.="</table>";

    return addslashes($hint);
  }

  function print_master_status ($ms) {
    $hint="";
    $hint.="<table class='master-status-hint table table-condensed'>";
    foreach ($ms as $k=>$v ) {
      $td = "<td>".$k."</td>";
      $td.= "<td>".htmlentities($v, ENT_QUOTES)."</td>";
      $hint.="<tr>{$td}</tr>";
    }
    $hint.="</table>";

    return addslashes($hint);
  }

  function users_compare (array $a, array $b) {
    //Функция необходима для array_udiff
    return strcmp($a['login'], $b['login'] );
  }

?>