<?php
  include_once "config.php";

  layout_reconfigure_main();


  function check_export_dir($dir_name) {
    //Проверяем, существует ли директория для экспорта, и пробуем ее создать
    global $config;
    $path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/{$dir_name}/";
    if (!file_exists($path)) {
      if (!mkdir($path, 0, true)) return false;        //
    }
    return true;
  }

  function layout_reconfigure_main() {
    global $config;

    try {
      echo "<script src='js/jquery.webui-popover.js'></script>";

      //--------------- Заголовок страницы ---------------
      echo "<div class='page-header'>\n";
      echo "<h2>Реконфигурация REJIK<br/><small>Данная страница отображает результат экспорта банлистов и статус реконфигурации SQUID</small></h2>\n";
      echo "</div>\n";

      $master_cfg = HealthPanel::get_master_config();
      $rejik = new rejik_worker ($config['rejik_db'], $master_cfg);

      //Проверка возможность записи
      if (!check_export_dir('users')) {
        echo "<div class='alert alert-danger'><b>Ошибка!</b> Не могу создать каталог <b>users</b></div>\n";
        exit;
      }

      //Проверка возможность записи
      if (!check_export_dir('banlists')) {
        echo "<div class='alert alert-danger'><b>Ошибка!</b> Не могу создать каталог <b>banlists</b></div>\n";
        exit;
      }

      echo "<div id='panel_users' class='panel panel-default'>\n";
      echo "<div class='panel-heading'>\n <span>Формирование списков правил</span>\n </div>\n";
      echo "<div class='panel-body'>\n";
      echo "<div class='panel panel-default'>\n";
      echo "<table class='table table-hover'>\n";
      echo "<tr><th>Банлист</th><th>пользователей / ссылок</th><th>Статус</th></tr>\n";
      // -----------------------------------------

      $time_start = microtime(true); // Замеряем время

      $u_path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/users/";
      $b_path=$_SERVER['DOCUMENT_ROOT']."/{$config ['proj_name']}/banlists/";
      $banlists = $rejik->banlists_get_list();

      foreach ($banlists as $value) {
        echo "<tr>\n";
        echo "<td>{$value}</td>\n";

        $err_flag=false;

        try {
          $users_time_start = microtime(true); // Замеряем время

          $users_count = $rejik->users_acl_export ($value, $u_path);

          $users_time = microtime(true) - $users_time_start; //Считаем затраченное время
        } catch (exception $e) {
          //echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}</div>\n";
          $err_msg  = $e->getMessage();
          $err_code = $e->getCode();
          $err_flag = true;
        }

        try {
          $urls_time_start = microtime(true); // Замеряем время
          $urls_count = $rejik->banlist_export ($value, $b_path);
          $urls_time = microtime(true) - $urls_time_start;
        } catch (exception $e) {
          echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}</div>\n";
          $err_msg  = $e->getMessage();
          $err_code = $e->getCode();
          $err_flag = true;
        }

        if ($err_flag) {
          echo "<td>0 / 0</td>\n";
          echo "<td id='reconfig-err-{$value}' class='text-danger'><div class='error-container'><span class='glyphicon glyphicon-remove'></span> Ошибка #{$err_code}</div></td>\n";
          echo "<script>$('#reconfig-err-{$value}').webuiPopover({content:'<small>".addslashes($err_msg)."</small>', title: 'Ошибка', trigger: 'hover', placement: 'right-top'});  </script>";
        } else {
          echo "<td>{$users_count} / {$urls_count}<br/><small>".round($users_time,3)." / ".round($urls_time,3)."</small></td>\n";
          echo "<td><span class='glyphicon glyphicon-ok'></span> OK</td>\n";
        }
        echo "</tr>\n";
      }

      $time_end = microtime(true);
      $time = $time_end - $time_start;

      if (isset($err_flag) && $err_flag == true) {
        Logger::add (46, "Реконфигурация выполнена с ошибкой");
      } else {
        Logger::add (45, "Реконфигурация выполнена успешно");
      }
      // -----------------------------------------

      echo "</table>\n";
      echo "</div>\n"; //of panel
      echo "</div>\n"; //of panel-body
      echo "</div>\n"; //of panel
      echo "<div class='alert alert-warning'>Данный скрипт выполнялся {$time} сек.</div>\n";

    } catch (mysql_exception $e) {
      echo "<div class='alert alert-danger'><b>Ошибка SQL!</b> {$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
    } catch (exception $e) {
      echo "<div class='alert alert-danger'><b>Неопознанная ошибка!</b><br/><pre>{$e->getTraceAsString()}</pre></div>\n";
    }
  }

?>
