<?php
function CheckPermissions() {
  //Данная функция проверяет права доступа к БД, и выполняет различные тесты.
  //В случае, если один из тестов не выполняется, пользователь получит уведомление.
  global $config;

  try {
    //Подключаемся к REJIK DB
    $rjk = new rejik_worker($config['rejik_db']);
    $rjk->do_query("SHOW SLAVE STATUS;");

    $res = $rjk->do_query("SHOW VARIABLES LIKE 'read_only';", AS_ROW);
    $read_only_mode = isset($res[1]) ? (strtolower($res[1])=="on" ? TRUE : FALSE) : FALSE;
    if ($read_only_mode) {
      echo "<div class='alert alert-info'><b>Внимание!</b><br>База данных RDB подключена в режиме 'только для чтения'";
      echo "</div>";
    }

  } catch (mysql_exception $me) {
    if ($me->getCode() == 1227) {
      echo "<div class='alert alert-warning'>".
        "<b>Внимание!</b><br>".
        "При соединении с REJIK DB возникла ошибка:<br>".
        "<i><small>{$me->getCode()} {$me->getMessage()}</small></i><br><br>".
        "Для дальнейшей работы скрипта, пользователю <b>{$config['rejik_db'][1]}</b> нужно предоставить привилегии:<br>".
        "<b>REPLICATION SLAVE и REPLICATION CLIENT</b><br><br>".
        "Для устранения ошибки выполните на REJIK DB следующий скрипт:<br>".
        "<code>GRANT REPLICATION CLIENT, REPLICATION SLAVE ON *.* TO '{$config['rejik_db'][1]}'@'{$config['rejik_db'][0]}';</code>".
        "</div>";
    } elseif ($me->getCode() == 1045){
      echo "<div class='alert alert-danger'><b>Самопроверка выявила ошибку. Недостаточно прав для доступа в REJIK DB</b><br>{$me->getCode()} : {$me->getMessage()}<br/></div>\n";
    } else {
      echo "<div class='alert alert-danger'><b>Самопроверка выявила ошибку при выполнении запроса:</b><br>{$me->getCode()} : {$me->getMessage()}</div>\n";
    }
  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка при самопроверке (Функция 'CheckPermissions'):</b><br>{$e->getCode()} : {$e->getMessage()}</div>\n";
  }

  //В этом блоке проверяем права на запись в следующие папки:
  // {rejik_web_dir}/banlists/
  // {rejik_web_dir}/users/
  // {rejik_web_dir}/cron/

  try {
    $project_dir = $_SERVER['DOCUMENT_ROOT']."/".$config['proj_name'];
    $dirs = array ($project_dir."/banlists/",
      $project_dir."/users/",
      $project_dir."/cron/",
      $project_dir);
    $perms = cp($dirs);
    foreach ($perms as $row) {
      $state = $row[1];
      if ($state != 'OK') {
        echo "<div class='alert alert-warning'><b>Вниминие!</b> \n";
        if ($state == "READ_ERROR") {
          echo "Папка " . $row[0] . " недоступна для чтения. (" . $row[3] . " " . $row[4] . ")";
        } elseif ($state == "WRITE_ERROR") {
          echo "Папка " . $row[0] . " недоступна для записи. (" . $row[3] . " " . $row[4] . ")";
        } elseif ($state == "CREATE_ERROR") {
          echo "Не могу создать папку " . $row[0] ." (" . $row[3] . " " . $row[4] . ")";
        }
        echo "<br>";
        if ($row[2] != "") echo "Ошибка: <i>{$row[2]}</i>\n";
        echo "</div>\n";
      }
    }

  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
  }
}

function cp($objects, $type = "d") {
  //error_reporting(E_ALL);
  //ini_set('display_errors', 1);

  if (!is_array($objects)) return FALSE;

  //Фунцкия принимает на входе массив, содержащий пути до файлов или директорий
  clearstatcache();
  $res = array(); $i=0;
  //Перебираем входной массив
  foreach ($objects as $obj) {
    //Проверяем, существует ли обьект ...
    if (!file_exists($obj)) {
      // ... если нет, то пытаемся создать
      if (!@mkdir($obj)) {
        //Если не получилось создать, то генерируем ошибку и переходим к след. обьекту
        $e = error_get_last();
        $res[$i] = array($obj, "CREATE_ERROR", $e['message'], "", "");
        $i++;
        continue;
      }
    }

    $perms = substr(sprintf('%o', fileperms($obj)), -4);
    $owners = fileowner ($obj).":".filegroup($obj);

    //Если обьект существует, проверяем его на чтение
    if (!is_readable($obj)) {
      //Если не получилось прочитать, то генерируем ошибку и переходим к след. обьекту
      $e = error_get_last();

      $res[$i] = array($obj, "READ_ERROR", $e['message'], $perms, $owners);
      $i++;
      continue;
    }

    //Если обьект существует, проверяем его на запись
    if (!is_writeable($obj)) {
      //Если не получилось записать, то генерируем ошибку и переходим к след. обьекту
      $e = error_get_last();
      $res[$i] = array($obj, "WRITE_ERROR", $e['message'], $perms, $owners);
      $i++;
      continue;
    }

    //Если все прошло ОК
    $res[$i] = array($obj, "OK", "-", $perms, $owners);
    $i++;
  }

  //echo "<pre>"; print_r($res); echo "</pre>";

  return $res;
}

CheckPermissions();
?>