<?php
  include_once "../config.php";
  include_once "../log.php";
  require_once "../classes.php";
  global $config;
	
  function create_logfile ($full_path, $message) {
    if (!($hdl=fopen($full_path, "w"))) return false;    
    fwrite($hdl, date("Y-m-d H:i:s")." ".$message);
    fclose($hdl);
    return true;
  };
  
  //Инициализация подключений к БД и логера
  $rejik = new rejik_worker ($config['rejik_db']);
  logger::init($rejik->sql);

  //Обьявляем пути до файлов
  $root_path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/";
  $b_path = $root_path."banlists/";
  $u_path = $root_path."users/";
  $l_full_path = $root_path."cron/lastcheck.log"; 

  //echo "Start...\n";

  
  //Перебираем все банлисты в базе
  $banlists = $rejik->banlists_get();
  $error_flag = false;
  foreach ($banlists as $bl) {
    if (!file_exists($b_path.$bl."/urls")) {
      echo "[{$bl}] banlist not found!\n";
      continue;
    }

    $db_crc = strtolower($rejik->banlist_get_crc($bl));
    $file_crc = strtolower(sha1_file($b_path.$bl."/urls"));

    if ($db_crc!=$file_crc) {
  	  echo "[{$bl}] banlist checksum error!\n";
      $error_flag = true;
    }
  }
  echo "Banlist check - ".(($error_flag) ? "ERROR" : "Ok")."\n";
  
  //Перебираем всех пользователей
  $error_flag = false;
  foreach ($banlists as $bl) {
    if (!file_exists($u_path.$bl)) {
      echo "[{$bl}] userlist not found!\n";
      continue;
    }

    $db_crc = strtolower($rejik->banlist_get_user_crc($bl));
    $file_crc = strtolower(sha1_file($u_path.$bl));

    if ($db_crc!=$file_crc) {
      echo "[{$bl}] userlist checksum error!\n";
      $error_flag = true;
    }
  }
  echo "Userlist check - ".(($error_flag) ? "ERROR" : "Ok")."\n";


  $logfile_result = create_logfile (
                                    $l_full_path,
                                    ($error_flag==true)?"ERROR":"OK"
                                   );
  if (!$logfile_result) echo "Save Logfile error!!!\n";
?>