<?php
  include_once "config.php";
  include_once "classes/HealthPanel.php";
  global $config;

  try {
    $master_cfg = HealthPanel::get_master_config();
    $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);

    //Проверяем, зарегистрирован пользователь в системе
    if ( ($rejik->is_user($user)) === FALSE ) {
      echo "<h1>Пользователь {$user} не найден в базе SAMS</h1>\n";
      return;
    }

    //Получаем список бан-листов, которые не применяются к пользователю.
    $user_banlists = $rejik->user_acl_get($user);
    if ($user_banlists == 0) $user_banlists = array();
    //echo "<pre>"; print_r($user_banlists); echo "</pre>";

    //Получаем список банлистов
    $banlists = $rejik->banlists_get();

    if ($banlists==0) {
      echo "<div class='alert alert-danger'>В базе нет ни одного бан-листа.</div>\n";
      return;
    }

    $userinfo = $rejik->user_info($user);

    //Выводим красивую табличку
    echo "<div class='page-header'><h1>Пользователь <i>{$user}</i></h1>";
    echo "<span>{$userinfo['name']}</span>\n";
    echo "</div>\n";

    echo "<h4>К каким категориям будет иметь доступ пользователь <b>{$user}</b>:</h2>\n";
    echo "<div class='panel panel-default'>\n";

    echo "<form id='acl' action='?action=getuser&user=$user' method='POST'>\n";
    echo "<input class='hidden' name='p_action' value='set_user_acl' />\n";
    echo "<input class='hidden' name='user' value='$user' />\n";
    echo "<table class='table table-striped'>\n";
    echo "  <tr><th>Выбор</th><th>Бан-лист</th><th>Описание</th></tr>\n";

    foreach ($banlists as $row) {
      echo "<tr>\n";

      //Помечаем те бан-листы, которые не применяются к пользователю
      echo "  <td>\n";
      echo "    <input type='hidden' name='bl_{$row['name']}' value='0' />\n";
      echo "    <input type='CHECKBOX' ".(
        ( array_search($row['name'], $user_banlists)!==false ) ? "checked " : ""
        )."name='bl_{$row['name']}' value='1' />\n";
      echo "  </td>\n";

      echo "  <td>{$row['name']}</td>\n";
      echo "  <td>{$row['full_desc']}</td>\n";
      echo "</tr>\n";
    };

    //Выполняем, привязан ли пользователь к каким-либо удаленным банлистам.
    $banlists = $rejik->banlists_get_list();

    foreach ($user_banlists as $value) {
      //Если бан листа пользователя нет в глобальном списке
      if (array_search($value, $banlists)===false) {
        echo "<tr class='danger'>\n";
        echo "  <td></td>\n";
        echo "  <td><b><i>{$value}<i></b></td>\n";
        echo "  <td><b><i>Банлист был удален из системы</i></b></td>\n";
        echo "</tr>\n";
      }
    }

    echo "</table>\n";

    //Кнопка "Отправить"
    echo "</div>\n";
    echo "<button class='btn btn-primary' form='acl'>Применить</button>\n";
    echo "</form>\n";

  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
  }



?>