
<?php
  echo "<style>\n .wrapper { max-width: 1800px; }\n</style>\n";

	echo "<div class='page-header'>\n";
	echo "<h2>Журнал событий<br/><small>Отображает все события, связанные с изменением состояний банлистов и пользователей</small></h2>\n";
	echo "</div>\n";

  Logger::init();

  const LOG_EVENTS_ON_PAGE = 5;

  $count = Logger::get_length(); //Получаем количество записей в логе.
  
  //Высчитываем кольчество страниц и количество групп страниц
  $pages_count = ($count>LOG_EVENTS_ON_PAGE) ? floor($count/LOG_EVENTS_ON_PAGE) : 1; //Всего страниц
  if ($count>LOG_EVENTS_ON_PAGE and $count%LOG_EVENTS_ON_PAGE!=0) $pages_count++; //Остаток

  echo "<div class='row' style='margin-bottom: 20px;'>\n";
  echo "  <div class='col-sm-4'>\n";
  if ($pages_count!=1) print_pagenator($pages_count);
  echo "  </div>\n";
  echo "  <div class='col-sm-4'>\n";
  echo "      <p>Всего <b>{$count}</b> элементов.<br/>Отображать по 200 элементов на странице</p>\n";
  echo "  </div>\n";
  echo "  <div class='col-sm-4'>\n";
  echo "      <button class='btn btn-default btn-sm' style='width: 100%'>Обновить</button>\n";
  echo "  </div>\n";
  echo "</div>\n";
  echo "<div class='row'>\n";

  
  

  $log = Logger::get(10, LOG_EVENTS_ON_PAGE);
  //echo "<pre>"; print_r($log); echo "</pre>\n";
  
  echo "<table id='table-log' class='table table-bordered table-condensed'>\n";
  echo "  <tr><th>Дата и Время</th><th>Код</th><th>Сообщение</th><th>Пользователь</th><th>IP адрес</th></tr>\n";
  foreach ($log as $log_elem) {
    //`id`,`datentime`,`code`,`message`,`attribute`,`user_login`,`user_ip`,`crc`
    //  0           1      2         3           4            5         6     7
    echo "  <tr>\n";
    echo "    <td>{$log_elem[1]}</td>\n";
    echo "    <td>{$log_elem[2]}</td>\n";
    echo "    <td>{$log_elem[3]}</td>\n";
    echo "    <td>{$log_elem[5]}</td>\n";
    echo "    <td>{$log_elem[6]}</td>\n";
    echo "  </tr>\n";  
  }

  echo "</table>\n"; 

?>
  </div>
<?php

  Logger::stop();
?>