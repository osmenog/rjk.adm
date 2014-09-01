<?php
  include_once "config.php";
  global $config;

  try {
    $rejik = new rejik_worker ($config['rejik_db']);
    $list = $rejik->banlists_get(true);
  
    if ($list==0) {
      echo "<div class='alert alert-danger'>В базе нет ни одного бан-листа.</div>\n";
      return;
    }
  
    //echo "<pre>"; print_r($list); echo "</pre>";
    echo "
    <div class='blacklist_box'>
    <div class='page-header'>
    <h2>Бан-листы</h2>
    </div>
    <div class='search'></div>
    <div class='banlists panel panel-default'>
    <table class='table table-striped'>
    <tr><th>Бан-лист</th><th>Описание</th></tr>\n";
  
    foreach ($list as $row) {
      echo "<tr>\n";
      echo "  <td> <a href='?action=getbanlist&banlist={$row['name']}'>{$row['name']}</a></td>\n";
      echo "  <td>{$row['short_desc']}</td>\n";
      echo "</tr>\n";
    };
  
    echo "</table>
    </div>
    </div>\n";
  } catch (mysql_exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка SQL!</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
  }
?>