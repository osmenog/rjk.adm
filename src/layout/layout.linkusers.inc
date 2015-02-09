<?php
  include_once "classes/HealthPanel.php";
  include_once 'classes/UserSync.php';

  //Получаем входные параметры, и фильтруем
  $action = $_GET['action']; // действие: подключение или отключение пользователей
  $pid = filter_input(INPUT_GET, 'pid', FILTER_VALIDATE_INT); //pid сервера, пользователи которого будут подключены
  if ($pid === False) throw new InvalidArgumentException("Неверно задан входящий параметр 'pid'", 1);

  $confirm = filter_input(INPUT_GET, 'confirm', FILTER_VALIDATE_INT); //флаг подтверждения операции
  if ($confirm === False) throw new InvalidArgumentException("Неверно задан входящий параметр 'confirm'", 1);

  global $config;

  //Инициализируем обработчик серверов.
  $hp = new HealthPanel();
  $servers = $hp->get_servers(); //Получаем список зарегистрированных в сестеме серверов

  //Проверяем, зарегистрирован ли сервер-донор в системе
  if ($servers->get_server_by_id($pid) === FALSE) throw new Exception ("Сервер с pid={$pid} не зарегистрирован в системе");

  //Проверяем, не является ли подключаемый сервер - локальным
  if ($hp->get_current_id() == $pid) throw new Exception ("Нельзя подключить пользователей к самому себе. local_pid={$hp->get_current_id()}; pid={$pid}");

  //Выполняем действие: "подключение пользователей"
  if ($action=='link_all_users') {
    try {
      //Подключаемся к RDB и получаем список пользователей для сервера, с которого будут подключены пользователи
      $master_cfg = HealthPanel::get_master_config();
      $rjk = new rejik_worker ($config['rejik_db'], $master_cfg);

      $users_data = $rjk->users_get(FIELDS_FULL, $pid);

      //Обрабатываем исключительную ситуацию
      if ($users_data === 0) throw new Exception("На сервере с pid={$pid} отсутствуют пользователи. Выполните синхронизацию.",999);

      //Получаем список пользователей, подключенных к текущему серверу.
      $linked_users = $rjk->users_get_linked($hp->get_current_id());
      $already_linked_users = array();

      if ($linked_users !== 0) {
        //Проверяем, были ли пользователи $users_data подключены к текущему прокси ранее. Помещаем таких пользователей в отдельный список.
        foreach ($users_data as $key => $row) {
          foreach ($linked_users as $linked_row) {
            if ($row['id'] == $linked_row['id']) {
              $already_linked_users[] = $row;
              unset ($users_data[$key]);
            }
          }
        }
      }

      if (count($users_data) == 0) {
        throw new Exception("Все пользователи с сервера pid={$pid} были подключены ранее",999);
      }

      //Если не был получен подтверждающий параметр, то выводим страницу подтверждения ...
      if ($confirm === NULL) {
        echo "<div class='page-header'>\n";
        echo "<h2>Подключение пользователей другого сервера</h2>\n";
        echo "</div>\n";

        echo "<div class='alert alert-warning'><b>Внимание!</b><br>Данная процедура создаст выбранных пользователей в БД SAMS</div>\n";

        //echo "<p>К серверу ".$hp->get_current_host()." (pid=".$hp->get_current_id().") будут подключены следующие пользователи:</p>";
        echo "<p>К серверу ".print_server_hint($hp->get_current_host(), $hp->get_current_id())." будут подключены следующие пользователи:</p>";

        //elseif ($action == 'unlink_all_users') {
        //  echo "<p>На сервере ".$hp->get_current_host()." (pid=".$hp->get_current_id().") будут отключены следующие пользователи:</p>";
        //}

        //Выводим пользователей, которые будут подключены.
        echo "<table>";
        echo "<table class='table table-condensed'>\n";
        echo "<thead>\n";
        echo "<tr>\n
            <th width='5%'>#</th>\n
            <th width='10%'>Логин</th>\n
            <th width='70%'>ФИО</th>\n
            <th width='15%'>ID прокси</th>\n
            </tr>\n";
        echo "</thead>\n";
        $counter = 1;
        foreach ($users_data as $key => $row) {
          echo "<tr>";
          echo "<td><small>{$counter}</small></td>";
          echo "<td>{$row['login']}</td>";
          echo "<td>{$row['name']}</td>";
          echo "<td>{$row['proxy_id']}</td>";
          echo "</tr>";
          $counter++;
        }
        echo "</table>";

        // Выводим пользователей, которые были подключены ранее.
        if (count($already_linked_users) != 0) {
          echo "<p>Пользователи, которые были подключены ранее, и будут пропущены:</p>";

          echo "<table>";
          echo "<table class='table table-condensed'>\n";
          echo "<thead>\n";
          echo "<tr>\n
            <th width='5%'>#</th>\n
            <th width='10%'>Логин</th>\n
            <th width='70%'>ФИО</th>\n
            <th width='15%'>ID прокси</th>\n
            </tr>\n";
          echo "</thead>\n";
          $counter = 1;
          foreach ($already_linked_users as $key => $row) {
            echo "<tr>";
            echo "<td><small>{$counter}</small></td>";
            echo "<td>{$row['login']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['proxy_id']}</td>";
            echo "</tr>";
            $counter++;
          }
          echo "</table>";
        }

        //Выводим кнопочку подтверждения
        echo "<button style='margin:40px 0px 100px 0px' class='btn btn-success' onClick='location.href=\"?action=link_all_users&pid={$pid}&confirm=1\"'>Подключить пользователей</button>\n";

      // ... иначе выполняем подключение пользователей
      } else {
        if ($confirm !== 1) throw new Exception ("Неверно задан входящий параметр 'confirm'");

        $sp = new sams_sync($config['server_id']);
        $linked_users = $sp->link_users_to_proxy($users_data);

        $created_users = $sp->create_sams_users($linked_users);

        $fixed_users = $sp->fix_linked_users_group($linked_users);

        $c = count($linked_users);
        $c2 = count ($created_users);
        echo "<div class='alert alert-success'>
                <b>Операция выполнена успешно!</b><br>
                {$c} пользователя были подключены к серверу.<br>
                {$c2} пользователя были созданы в SAMS.
              </div>";
      }

    } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка во время выполнения проверки перед подключением пользователей</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
    }
  }

  if ($action=='unlink_all_users') {
    try {

      $master_cfg = HealthPanel::get_master_config();
      $rjk = new rejik_worker ($config['rejik_db'], $master_cfg);

      //Получаем список ВСЕХ пользователей, подключенных к текущему серверу...
      $linked_users = $rjk->users_get_linked($hp->get_current_id());
      if ($linked_users === 0) throw new Exception("На сервере с pid={$pid} отсутствуют подключенные пользователи",999);

      //Выбираем пользователей, подключенных к данному серверу и имеющих нужный $pid
      $users_to_unlink = array();
      foreach ($linked_users as $row) {
        if ($row['proxy_id'] == $pid) $users_to_unlink[] = $row;
      }

      if ($confirm === NULL) {
        //Выводим страницу для подтверждения
        echo "<div class='page-header'>\n";
        echo "<h1>Подтвердите операцию</h1>\n";
        echo "</div>\n";
        echo "<p>На сервере ".$hp->get_current_host()." (pid=".$hp->get_current_id().") будут отключены следующие пользователи:</p>";

        echo "<table>";
        echo "<table class='table table-condensed'>\n";
        echo "<thead>\n";
        echo "<tr>\n
            <th width='5%'>#</th>\n
            <th width='10%'>Логин</th>\n
            <th width='70%'>ФИО</th>\n
            <th width='15%'>ID прокси</th>\n
            </tr>\n";
        echo "</thead>\n";
        $counter = 1;
        foreach ($users_to_unlink as $key => $row) {
          echo "<tr>";
          echo "<td><small>{$counter}</small></td>";
          echo "<td>{$row['login']}</td>";
          echo "<td>{$row['name']}</td>";
          echo "<td>{$row['proxy_id']}</td>";
          echo "</tr>";
          $counter++;
        }
        echo "</table>";

        //Выводим кнопочку подтверждения
        echo "<button style='margin:40px 0px 100px 0px' class='btn btn-danger' onClick='location.href=\"?action=unlink_all_users&pid={$pid}&confirm=1\"'>Отключить пользователей</button>\n";

      } else {
        if ($confirm !== 1) throw new Exception ("Неверно задан входящий параметр 'confirm'");

        $sp = new sams_sync($config['server_id']);
        $unlinked_users = $sp->unlink_users_from_proxy($users_to_unlink);
        $deleted_users = $sp->delete_sams_users($unlinked_users);
        $c = count($unlinked_users);
        $c2 = count($deleted_users);
        echo "<div class='alert alert-success'>
                <b>Операция выполнена успешно!</b><br>
                {$c} пользователей были отключены от сервера.<br>
                {$c2} пользователей былы удалены в SAMS
              </div>";
      }
    } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка во время выполнения проверки перед отключением пользователей</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
    }
  }
?>