<?php
include_once "../config.php";
include_once "../log.php";
require_once "../classes.php";
global $config;

$rejik = new rejik_worker ($config['rejik_db']);

$root_path=$_SERVER['DOCUMENT_ROOT']."/{$config['proj_name']}/";

$b_path = $root_path."banlists/";
$u_path = $root_path."users/";

echo "Start...\n";

//Перебираем все банлисты в базе
$banlists = $rejik->banlists_get();
foreach ($banlists as $bl) {
  $db_crc = $rejik->banlist_get_crc($bl);
  $file_crc = sha1_file($b_path.$bl."/urls", true);
  echo "[{$bl}] ".($db_crc==$file_crc ? "=" : "<>")." ".bin2hex($db_crc)." | ".bin2hex($file_crc)."\n";
}


?>