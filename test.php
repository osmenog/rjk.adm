<?php
  include_once "config.php";
  include_once "classes.php";
  include_once "log.php";

  global $config;

  $rjk = new rejik_worker ($config['rejik_db']);
  $url = "http://hui.ad.google.com/search?asd=1";

  echo $rjk->check_url($url);

?>
