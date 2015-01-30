<?php
  include_once "config.php";
  global $config;

  //$banlists = $rejik->banlists_get();

  function check_export_dir($dir_name) {
    //Проверяем, существует ли директория для экспорта, и пробуем ее создать
    global $config;
    $path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/{$dir_name}/";
    if (!file_exists($path)) {
      if (!mkdir($path, 0, true)) return false;        //
    }
    return true;
  }


  try {
    //--------------- Заголовок страницы ---------------
    echo "<div class='page-header'>\n";
    echo "<h2>Реконфигурация REJIK<br/><small>Данная страница отображает результат экспорта банлистов и статус реконфигурации SQUID</small></h2>\n";
    echo "</div>\n";
    
    $rejik = new rejik_worker ($config['rejik_db']);

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
                $users_time = microtime(true) - $users_time_start;
              } catch (exception $e) {
                echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}</div>\n";
                $err_flag = true;
              }

              try {
                $urls_time_start = microtime(true); // Замеряем время
                $urls_count = $rejik->banlist_export ($value, $b_path);
                $urls_time = microtime(true) - $urls_time_start; 
              } catch (exception $e) {
                echo "<div class='alert alert-danger'><b>Ошибка</b> {$e->getCode()} : {$e->getMessage()}</div>\n";
                $err_flag = true;
              }

              if ($err_flag) {
                echo "<td>0 / 0</td>\n";
                echo "<td><span class='glyphicon glyphicon-remove'></span> Ошибка!</td>\n";
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






// //4. Создаем списки пользователей для каждого бан листа
// $hdl = fopen("{$path}/{$value}", "w");
// if(!$hdl) {
// echo "<h4 class='text-danger'><span class='glyphicon glyphicon-remove'></span> Список пользователей для <b>{$value}</b> не создан</h4>\n";
// echo "<p>Не могу создать файл {$value}</p>\n";
// continue;
// }
// echo "<p><h4 class='text-success'><span class='glyphicon glyphicon-ok'></span> Список пользователей для <b>{$value}</b> успешно создан!</h4></p>\n";
// }

//     //--------------- Формирование списков пользователей -----------------------------------------------*

  
// echo "<table class='table table-hover'>\n";
//           echo "  <tr><th>Логин</th><th>ФИО</th></tr>\n";
//           $banlists = $rejik->banlists_get();
//           foreach ($banlists as $value) {
//             $users = $rejik->banlist_get_users($value);
//             $path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/users/";
//             if(!fopen("{$path}/{$value}", "w")) {
//               echo "<tr><td>{$value}</td>"
//               echo "<h4 class='text-danger'><span class='glyphicon glyphicon-remove'></span> Список пользователей для <b>{$value}</b> не создан</h4>\n";
//               echo "<p>Не могу создать файл {$value}</p>\n";
//               continue;
//             }else{
//               //Построчно записываем в файл список пользователей.
//               if (!empty($users)) {
//                 foreach ($users as $row) {fwrite($hdl, $row."\r\n"); }
//               }
//               fclose($hdl);
//             }
//             echo "<p><h4 class='text-success'><span class='glyphicon glyphicon-ok'></span> Список пользователей для <b>{$value}</b> успешно создан!</h4></p>\n";
//           }
//           echo "<tr>\n";
//           echo "  <td>123</td>\n";
//           echo "  <td>123</td>\n";
//           echo "</tr>\n";
//           echo "</table>\n";

//     //--------------- Формирование банлистов -----------------------------------------------*
//     echo "<div id='panel_users' class='panel panel-default'>\n";
//       echo "<div class='panel-heading'>\n <span>Формирование банлистов</span>\n </div>\n";
//       echo "<div class='panel-body'>\n";
//         echo "<div class='panel panel-default'>\n";
//           echo "<table class='table table-hover'>\n";
//             echo "<tr><th>Логин</th><th>ФИО</th></tr>\n";
//             echo "<tr>\n";
//             echo "  <td>123</td>\n";
//             echo "  <td>123</td>\n";
//             echo "</tr>\n";
//           echo "</table>\n";
//         echo "</div>\n"; //of panel
//       echo "</div>\n"; //of panel-body
//     echo "</div>\n"; //of panel


// $banlists = $rejik->banlists_get();

// //1. Подготовка
// $path=$_SERVER['DOCUMENT_ROOT']."/{$config ['proj_name']}/users/";
// //$p = $path."/{$value['name']}/";
// if (!file_exists($path)) {
// if (!mkdir($path, 0, true)) {
// echo "<div class='alert alert-danger'><b>Ошибка!</b> Не могу создать каталог {$p}</div>\n";
// return;
// }
// }

// //2. Для каждого банлиста получаем список юзеров
// foreach ($banlists as $value) {
// $users = $rejik->banlist_get_users($value);

// //4. Создаем списки пользователей для каждого бан листа
// $hdl = fopen("{$path}/{$value}", "w");
// if(!$hdl) {
// echo "<h4 class='text-danger'><span class='glyphicon glyphicon-remove'></span> Список пользователей для <b>{$value}</b> не создан</h4>\n";
// echo "<p>Не могу создать файл {$value}</p>\n";
// continue;
// }

// //Построчно записываем в файл список пользователей.
// if (!empty($users)) {
// foreach ($users as $row) {
// fwrite($hdl, $row."\r\n");
// }
// }
// fclose($hdl); 


// echo "<p><h4 class='text-success'><span class='glyphicon glyphicon-ok'></span> Список пользователей для <b>{$value}</b> успешно создан!</h4></p>\n";
// }

// //3. Создаем списки УРЛов

// //3.1 Подготовка
// $path=$_SERVER['DOCUMENT_ROOT']."/{$config ['proj_name']}/banlists/";
// //$p = $path."{$value['name']}/";
// if (!file_exists($path)) {
// if (!mkdir($path, 0, true)) {
// echo "<div class='alert alert-danger'><b>Ошибка!</b> Не могу создать каталог {$path}</div>\n";
// return;
// }
// }

// //3.2 Процесс
// echo "<h2>Создание банлистов:</h2>\n";
// foreach ($banlists as $value) {
// try {
// $result = $rejik->banlist_export ($value, $path); 
// } catch (exception $e) {
// echo "<div class='alert alert-danger'><b>Ошибка</b><br/>{$e->getCode()} : {$e->getMessage()}</div>\n";
// if ($e->getCode() == 112) continue; 
// }
// $urls = -1;
// echo "<p><h4 class='text-success'><span class='glyphicon glyphicon-ok'></span> Банлист <b>{$value}</b> успешно создан (".(($urls == 0) ? 0 : count ($urls))." записей)</h4></p>\n"; 
// }

// } catch (exception $e) {
// echo "<div class='alert alert-danger'><b>Ошибка</b>Вылетело исключение.<br/>{$e->getCode()} : {$e->getMessage()}<br/><pre>{$e->getTraceAsString()}</pre></div>\n";
// }   
?>
