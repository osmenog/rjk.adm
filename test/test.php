<?php
  include_once "config.php";
  include_once "classes.php";
  include_once "log.php";

  global $config;

  $rjk = new rejik_worker ($config['rejik_db']);
  $url = "http://hui.ad.google.com/s1earch?asd=1";

  //echo $rjk->check_url($url);
  $dup = $rjk->find_duplicate($url);
  if ($dup!=0 and is_array($dup)) {
    echo "<pre>"; print_r($dup); echo "</pre>"; 
  } else {
    echo "dub not found";
  }

?>
