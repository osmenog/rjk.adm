<?php
  include_once "config.php";
  global $config;

  //Выводим список пользователей, зарегистрированных на прокси
  $prx = new proxy_worker ($config['sams_db']);
  $list = $prx->get_userslist();
  echo "  <div class='page-header'>\n";
  echo "    <h2>Список пользователей</h2>\n";
  echo "  </div>\n";
  echo "    <div class='search-box input-group'>\n";
  echo "      <input type='text' class='form-control' placeholder='Введите имя, фамилию или логин пользователя' disabled>\n";
  echo "      <span class='input-group-btn'>\n";
  echo "        <button class='btn btn-default' type='button'>Искать</button>\n";
  echo "      </span>\n";
  echo "    </div><!-- /input-group -->\n";
?>
</br>
<ul class="nav nav-tabs nav-justified">
  <li role="presentation" class="active"><a href="#">Home</a></li>
  <li role="presentation"><a href="#">Profile</a></li>
  <li role="presentation"><a href="#">Messages</a></li>
</ul>

<?php

  echo "  <div class='userlist panel panel-default'>\n";
  echo "    <table class='table table-striped table-condensed'>\n";
  echo "      <tr><th>Выбор</th><th>Логин</th><th>ФИО</th></tr>\n\n";
  foreach ($list as $row) {
    echo "<tr>\n  <td></td>\n";
    echo "  <td><a href='?action=getuser&user={$row['nick']}'>".$row['nick']."</a></td>\n";
    echo "  <td>{$row['family']} {$row['name']} {$row['soname']}</td>\n</tr>\n";
  };
  echo "	</table>\n";
  echo "</div>\n";
?>