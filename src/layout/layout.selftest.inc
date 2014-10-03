<?php
  include_once "config.php";
  global $config;

  //--------------- Заголовок страницы ---------------
	echo "<div class='page-header'>\n";
	echo "<h2>Самопроверка<br/><small>Данный раздел отображает информацию о несанкционированных изменениях банлистов и списков пользователей</small></h2>\n";
  echo "</div>\n";

  try {
    $checker = new rejik_worker ($config['rejik_db']);
    $checker_sql = $checker->sql;
  
    $st = CheckSelfTestResult();

    echo "<h4>Последняя проверка: <b>{$st[0]} {$st[1]}</b></h4>\n";
    echo "<h4>Результат: ".($st[2]=="OK" ? "<b>проверка выполнена успешно</b>" : "<b>были выявлены ошибки</b>")."</h4>\n";
    echo "<br/>\n";
    
    $res = $checker_sql->query("SELECT `file`,`msg` FROM `checker` WHERE `lastcheck`='{$st[0]} {$st[1]}'");

    if (($st[2]!="OK") AND ($res->num_rows!=0)) {
      echo "<table class='table table-bordered table-condensed'>\n";
      echo "  <tr><th>Файл</th><th>Описание</th></tr>\n";
      while ($row = $res->fetch_array()) {
        echo "  <tr>\n";
        echo "    <td>{$row[0]}</td>\n";
        echo "    <td>{$row[1]}</td>\n";
        echo "  </tr>\n";
      }
      echo "</table>\n";  
    }
    
    echo "<br/>\n";
    echo "<center>\n";
    echo "<button class='btn btn-success btn-lg' onClick='location.href=\"?action=check\"'>Выполнить проверку</button>  \n";
    echo "</center>\n";
  } catch (exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b>Вылетело исключение.<br/>{$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }

  $checker->closedb();
?>