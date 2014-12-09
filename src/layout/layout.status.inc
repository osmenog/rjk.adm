<?php


  //Фильтруем входные данные
  $id = filter_input(INPUT_GET, 'switch_master_to', FILTER_VALIDATE_INT);
  if ($id === False) throw new InvalidArgumentException("Неверно задан входящий параметр 'switch_master_to'", 1);

  $sync_with_sams = filter_input(INPUT_GET, 'sync_with_sams', FILTER_VALIDATE_INT);
  if ($sync_with_sams === 1) {
    include_once 'classes/UserSync.php';
    do_sync();
  }

  include_once "config.php";
  include_once "classes/ServersList.php";
  include_once "classes/RejikServer.php";
  include_once "classes/HealthPanel.php";

// ----------------------------------------------------------------------------------------------
  function CheckPermissions() {
    //Данная функция проверяет права доступа к БД, и выполняет различные тесты.
    //В случае, если один из тестов не выполняется, пользователь получит уведомление.
    global $config;
    try {
      //Подключаемся к REJIK DB
      $rjk = new rejik_worker($config['rejik_db']);
      $res = $rjk->sql->query("SHOW SLAVE STATUS;");
      if (!$res) {
        throw new mysql_exception ($rjk->sql->error, $rjk->sql->errno);
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
      } else {
        echo "<div class='alert alert-danger'><b>Ошибка</b> {$me->getCode()} : {$me->getMessage()}<br/></div>\n";
      }
    } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
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
      cp($dirs);
    } catch (Exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
    }
  }

  function cp($objects, $type = "d") {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
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

    echo "<pre>"; print_r($res); echo "</pre>";

    return $res;
  }

// ----------------------------------------------------------------------------------------------

  try {

    CheckPermissions();

    global $config;

    //Инициализируем главный обьект
    $hp = new HealthPanel();

    //Если пришла команда смены мастера
    if ($id !== NULL) {
      $hp->switch_master($id);
      exit;
    }

    //Загружаем состояние обьектов
    //$hp->restore_session();

    echo "<div class='page-header'>\n";
    echo "<h1>\nПанель управления</br>\n";
    echo "<small>" . $hp->get_local_server_name() . "</small>\n</h1>\n";
    echo "<small>Внутренний идентификатор: " . $config['server_id'] . "</small>\n";
    echo "</div>\n";

    //---------------------------------------------------------------------------------
    // СОСТОЯНИЕ РАБОТЫ СЛУЖБ ТЕКУЩЕГО СЕРВЕРА
    //---------------------------------------------------------------------------------
    //echo "<h3>Состояние работы служб текущего сервера:</h3>\n";
    //echo "<ul>\n";
    //echo "<li><b>Служба SQUID:</b> --ЗАГЛУШКА--</li>\n";
    //echo "<li><b>Служба SAMS:</b> --ЗАГЛУШКА--</li>\n";
    //echo "<li><b>Сервер БД для записи:</b> ".$hp->get_master_server()."</li>\n";
    //echo "</ul>\n";

    //---------------------------------------------------------------------------------
    // СОСТОЯНИЕ СИНХРОНИЗАЦИИ ПОЛЬЗОВАТЕЛЕЙ
    //---------------------------------------------------------------------------------
    echo "<h3>Пользователи:</h3>\n";
    echo "<p>Всего пользователей в REJIK DB: " . "--0--" . "<br/>\n";
    echo "<ul>\n<li>proxy2: " . "--80--" . "</li>\n";
    echo "<li>proxy.msk: " . "--80--" . "</li>\n";
    echo "</ul>\n";
    echo "</p>\n";
    echo "<p>Последняя синхронизация REJIK DB с локальным SAMS: 10.10.10 Успешно</p>";
    echo "<button class='btn btn-success btn-xs' onClick='location.href=\"?action=status&sync_with_sams=1\"'>Синхронизировать пользователей</button>\n";

    echo "<p>Всего пользователей текущего сервера: 80";
    echo "<ul>";
    echo "<li>--80-- внутренних пользователей</li>";
    echo "<li><a href='#'>подключить</a> пользователей proxy.msk</li>";
    echo "</ul>\n";

    //---------------------------------------------------------------------------------
    // СОСТОЯНИЕ РЕПЛИЦАЦИИ БАЗЫ ДАННЫХ
    //---------------------------------------------------------------------------------
    echo "<div class='row'>\n";
    echo "<div class='col-md-8'>";
    echo "<h3>Состояние репликации:</h3>\n";
    echo "</div>";
    echo "<div class='col-md-4 text-right'>";
    echo "<button class='btn btn-success btn-sm' id='servers_check_btn' onClick='location.href=\"?action=servers_check\"'>Выполнить проверку</button>  \n";
    echo "</div>";
    echo "</div>\n";


    //Стартуем проверку доступности серверов
    $hp->check_availability();
    //Сохраняем результаты проверки в открытой сессии
    //$hp->save_session();

    echo "<div class='panel panel-default'>\n";
    echo "<table class='table table-condensed'>\n";
    echo "<thead>\n";
    echo "<tr>\n
          <th width='40%'>Имя сервера</th>\n
          <th width='10%'>Доступность</th>\n
          <th width='40%'>Статус</th>\n
          <th width='10%'> </th>\n
          </tr>\n";
    echo "</thead>\n";
    //echo "<pre>\n"; foreach ($hp->servers_list as $s) { echo "{$s}\n"; var_dump($s->sql_error); }; echo "</pre>\n";
    foreach ($hp->get_servers() as $value) {

      echo "<tr>\n";
      echo "<td><span>{$value}</span>\n";
      $srv_id = $value->get_id();
      if ($value->mode() == WORK_MODE_MASTER) {
        echo " <span class='label label-danger'>МАСТЕР</span>";
      } elseif ($value->mode() == WORK_MODE_SLAVE) {
        echo " <span class='label label-warning'>ПОДЧИНЕННЫЙ</span>";
      }
      echo "</td>\n";

      if ($avaiable = $value->is_connected()) {
        echo "<td class='text-success'>ДОСТУПЕН</td>";

        $err = $hp->get_repl_errors($value);
        if (empty($err)) {
          echo "<td class='text-success'>OK</td>";
        } else {
          echo "<td class='text-danger'>\n";
          echo "<a href='#srv{$srv_id}' data-toggle='collapse' data-target='#srv{$srv_id}'>\n";
          echo "<span class='glyphicon glyphicon-remove'></span> ОШИБКА\n";
          echo "</a>\n";
          echo "</td>\n";
        }
      } else {

        echo "<td class='text-danger'>НЕ ДОСТУПЕН</td>\n";
        $err = $value->get_error();
        //echo "<pre>"; $value->get_error(); echo "</pre>";
        echo "<td><span style='font-size: 8pt;'>{$err['errno']}: " . iconv("CP1251", "UTF-8", $err['error']) . "</span></td>\n";
      }

      $wm = $value->mode();
      if (($wm == WORK_MODE_SLAVE || $wm == WORK_MODE_UNDEFINED) && $avaiable) {
        echo "<td><button type='button' onClick='location.href=\"?action=status&switch_master_to=" . $value->get_id() . "\"' class='btn btn-primary btn-xs'> Сделать мастером</button></td>\n";
      } else {
        echo "<td> </td>\n";
      }

      echo "</tr>\n";

      if (!empty($err)) {
        $data_content = '';
        foreach ($err as $k => $v) $data_content .= "{$k}: {$v}</br>\n";
        echo "<tr class='hiddenRow'><td colspan=4>
          <div id='srv{$srv_id}' class='collapse'>
            <div class='alert alert-danger hiddenAlert'>
              <small>{$data_content}</small>
            </div>
          </div>
        </td></tr>\n";
      }
      //echo "<tr><td colspan=4>"; foreach ($err as $k => $v) echo "{$k} = {$v}</br>"; echo "</td></tr>\n";
    }
    echo "</table>\n";
    echo "</div>\n"; // of panel
  } catch (Exception $e) {
    echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}<br/></div>\n";
  }


class check_perms {
  function check_perms($curpath){
    if($curpath){
      if($dir = @dir($curpath)){
        while ($file = $dir-> read()){
          if($file != "." && $file != ".."){

            $perms = substr(decoct(fileperms($curpath.$file)), 2);

            if(is_dir($curpath.$file)){// если есть поддиректории то продолжаем рекурсию
              $this-> check_perms($curpath.$file."/");

              if($perms>775 || $perms<775){
                if(!@chmod($curpath.$file, 0775)){
                  $this->perms_d775 .= $curpath.$file."\n";
                }
              }
            }else{// файл

              $ext = array_reverse(explode('.', $file));

              if($ext['0']=='php' && ($perms>775 || $perms<775)){
                if(!@chmod($curpath.$file, 0775)){
                  $this->perms_f775 .= $curpath.$file."\n";
                }
              }else if($ext['0']!='php' && ($perms>664 || $perms<664)){
                if(!@chmod($curpath.$file, 0664)){
                  $this->perms_f664 .= $curpath.$file."\n";
                }
              }
            }
            // пытаемся сменить группу
            /*if($GLOBALS['group']){
                $group = filegroup ($curpath.$file);
                if($group!=$GLOBALS['group'] && !@chgrp($curpath.$file, $GLOBALS['group'])){
                    $this->group .= $curpath.$file."\n";
                }
            }
            // пытаемся сменить владельца
            if($GLOBALS['owner']){
                $owner = fileowner ($curpath.$file);
                if($owner!=$GLOBALS['owner'] && !@chown($curpath.$file, $GLOBALS['owner'])){
                    $this->owner .= $curpath.$file."\n";
                }
            }*/
          }
        }
        $dir->close();
      }
    }
  }
}
?>