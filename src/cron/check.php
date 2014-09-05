<?php
  include_once $_SERVER['DOCUMENT_ROOT']."/rejik2"."/config.php";
  include_once $_SERVER['DOCUMENT_ROOT']."/rejik2"."/log.php";
  include_once $_SERVER['DOCUMENT_ROOT']."/rejik2"."/classes.php";
  global $config;
	
  function create_logfile ($full_path, $dt, $message) {
    if (!($hdl=fopen($full_path, "w"))) return false;    
    fwrite($hdl, $dt." ".$message);
    fclose($hdl);
    return true;
  };
  
  function checker_db_insert ($sql, $filename, $dt, $message) {
    $filename = mysql_real_escape_string ($filename);
    $query = "INSERT INTO `checker`(`file`, `lastcheck`, `msg`) VALUES ('$filename','$dt','$message');";
    $res = $sql->query($query);
    if (!$res) {
      //echo "[{$res}] sql error: ".$sql->errno." ".$sql->error."\n";
      return false;
    }
    return true;
  };

  //Инициализация подключений к БД и логера
  $rejik = new rejik_worker ($config['rejik_db']);
  logger::init();
  $checker_db = $rejik->sql;

  //Обьявляем пути до файлов
  $root_path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/";
  $b_path = $root_path."banlists/";
  $u_path = $root_path."users/";
  $l_full_path = $root_path."cron/lastcheck.log"; 

  $dt = date("Y-m-d H:i:s");
  //echo "Start...\n";

  //Перебираем все банлисты в базе
  $banlists = $rejik->banlists_get();
  $error_flag = false;
  
  foreach ($banlists as $bl) {
    $b_file = $b_path.$bl."/urls"; //Определяем имя файла для банлиста

    if (!file_exists($b_file)) {
      //echo "[{$b_file}] not found!\n";
      checker_db_insert ($checker_db, $b_file, $dt, 'not found');

      $error_flag = true;
      continue;
    }

    //Получаем значения контрольных сумм
    $db_crc = strtolower($rejik->banlist_get_crc($bl));
    $file_crc = strtolower(sha1_file($b_file));

    //Сравниваем контрольные суммы
    if ($db_crc!=$file_crc) {
  	  //echo "[{$b_file}] checksum error!\n";
      checker_db_insert ($checker_db, $b_file, $dt,'checksum error');
      Logger::add (32, "Checker выявил ошибку хэша в банлисте [$bl]", $bl, $dt);
      $error_flag = true;
    }
  }

  //echo "Banlist check - ".(($error_flag) ? "ERROR" : "Ok")."\n";
  
  //Перебираем всех пользователей
  $error_flag = false;
  foreach ($banlists as $bl) {
    $u_file = $u_path.$bl;
    if (!file_exists($u_file)) {
      //echo "[{$u_file}] not found!\n";
      checker_db_insert ($checker_db, $u_file, $dt,'not found');
      $error_flag = true;
      continue;
    }

    $db_crc = strtolower($rejik->banlist_get_user_crc($bl));
    $file_crc = strtolower(sha1_file($u_file));

    if ($db_crc!=$file_crc) {
      //echo "[{$u_file}] checksum error!\n";
      checker_db_insert ($checker_db, $u_file, $dt,'checksum error');
      Logger::add (33, "Checker выявил ошибку хэша в списке пользователей [$bl]", $bl, $dt);
      $error_flag = true;
    }
  }
  //echo "Userlist check - ".(($error_flag) ? "ERROR" : "Ok")."\n";
  if (!$error_flag) {
    Logger::add (31, "Checker успешно выполнил проверку по расписанию", "", $dt);
  }

  $logfile_result = create_logfile (
                                    $l_full_path, $dt,
                                    ($error_flag==true)?"ERROR":"OK"
                                   );

  if (!$logfile_result) echo "Save Logfile error!!!\n";

  $rejik->closedb();
?>