<?php
function dbg_print($obj) {
  echo "--connection--\n";
  echo (isset($obj->connect_errno) ? $obj->connect_errno:'-')."\n";
  echo (isset($obj->connect_error) ? $obj->connect_error:'-')."\n";
  echo "--errors--\n";
  echo (isset($obj->errno) ? $obj->errno:'-')."\n";
  echo (isset($obj->error) ? $obj->error:'-')."\n";
  echo "--state--\n";
  echo $obj->sqlstate;
}

$a1 = mysqli_init();
$a1->options(MYSQLI_OPT_CONNECT_TIMEOUT, 7);
@$a1->real_connect('osme-n', 'rosot', 'osme');

echo "<pre>"; echo $a1->thread_id; echo "</pre>";
echo "<pre>"; dbg_print ($a1); echo "</pre>";

// -----

$a2 = mysqli_init();
$a2->options(MYSQLI_OPT_CONNECT_TIMEOUT, 7);
@$a2->real_connect('FreeBSD_1', 'root', 'osme');

echo "<pre>"; echo $a2->thread_id; echo "</pre>";
echo "<pre>"; dbg_print ($a2); echo "</pre>";

// -----

echo "<pre>"; echo $a1->thread_id; echo "</pre>";
echo "<pre>"; dbg_print ($a1); echo "</pre>";

$a1->close();
$a2->close();
?>