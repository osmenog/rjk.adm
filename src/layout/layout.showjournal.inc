<?php
	echo "<div class='page-header'>\n";
	echo "<h2>Журнал событий<br/><small>Отображает все события, связанные с изменением состояний банлистов и пользователей</small></h2>\n";
	echo "</div>\n";

?>
  <div class='row' style='margin-bottom: 20px;'>
    <div class='col-sm-4'>
        <p>Странички</p>
    </div>
    <div class='col-sm-4'>
        <p>Отображать 200 элементов</p>
    </div>
    <div class='col-sm-4'>
        <button class='btn btn-default btn-sm' style='width: 100%'>Обновить</button>
    </div>
  </div>

  <div class='row'>
<?php
  echo "<table class='table table-bordered table-condensed'>\n";
  echo "  <tr><th>Дата</th><th>Код</th><th>Сообщение</th><th>Пользователь</th><th>IP адрес</th></tr>\n";
  echo "  <tr>\n";
  echo "    <td>1.01.01</td>\n";
  echo "    <td>01</td>\n";
  echo "    <td>Проверочное сообщение</td>\n";
  echo "    <td>Admin</td>\n";
  echo "    <td>192.18.0.1</td>\n";
  echo "  </tr>\n";
  echo "</table>\n"; 

?>
  </div>