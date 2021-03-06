<?php
  include_once "config.php";
  include_once "classes/RejikWorker.php";

  global $config;

  //Выводим список пользователей, зарегистрированных на прокси
  //$prx = new proxy_worker ($config['sams_db']);
  //$list = $prx->get_userslist();
  $master_cfg = HealthPanel::get_master_config();
  $rjk = new rejik_worker ($config['rejik_db'], $master_cfg);

  $list = $rjk->users_get(FIELDS_LOGINS_AND_ID);

  echo "  <div class='page-header'>\n";
  echo "    <h2>Список пользователей</h2>\n";
  echo "  </div>\n";
//fixme Раскомментировать, когда поиск будет работать нормально
//  echo "    <div class='search-box input-group'>\n";
//  echo "      <input type='text' class='form-control' placeholder='Введите имя, фамилию или логин пользователя' disabled>\n";
//  echo "      <span class='input-group-btn'>\n";
//  echo "        <button class='btn btn-default' type='button'>Искать</button>\n";
//  echo "      </span>\n";
//  echo "    </div><!-- /input-group -->\n";

//fixme Раскомментировать, когда синхронизация пользователей будет работать нормально
//</br>
//<ul class="nav nav-tabs nav-justified">
//  <li role="presentation" class="active"><a href="#">Home</a></li>
//  <li role="presentation"><a href="#">Profile</a></li>
//  <li role="presentation"><a href="#">Messages</a></li>
//</ul>

if ($list === 0) {
  echo "<div class='alert alert-warning'>В базе Rejik DB отсутствуют пользователи.<br>";
  echo "Рекомендуется выполнить синхронизацию c локальной БД SAMS:<br><br>";
  echo "<button class='btn btn-success' onClick='location.href=\"?action=status&sync_with_sams=1\"'>Синхронизировать пользователей</button>\n";
  echo "</div>";
} else {
  echo "  <div class='userlist panel panel-default'>\n";
  echo "    <table class='table table-striped table-condensed'>\n";
  echo "      <tr><th>Выбор</th><th>Логин</th><th>ФИО</th><th>Родитель</th></tr>\n\n";
  foreach ($list as $k => $row) {
    echo "<tr>\n  <td></td>\n";
    echo "  <td><a href='?action=getuser&user={$row['login']}'>".$row['login']."</a></td>\n";
    echo "  <td>{$row['name']}</td>\n";
    echo "<td>id={$row['proxy_id']}</td>\n</tr>\n";
  };
  echo "	</table>\n";
  echo "</div>\n";
}


?>