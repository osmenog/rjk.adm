<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

function err_handler() {
  $e = error_get_last();
  if ($e !== NULL) {
    echo "<pre>\n";  var_dump ($e); echo "</pre>\n";
    echo FriendlyErrorType($e['type']);
  }
}

function FriendlyErrorType($type)
{
  switch($type)
  {
    case E_ERROR: // 1 //
      return 'E_ERROR';
    case E_WARNING: // 2 //
      return 'E_WARNING';
    case E_PARSE: // 4 //
      return 'E_PARSE';
    case E_NOTICE: // 8 //
      return 'E_NOTICE';
    case E_CORE_ERROR: // 16 //
      return 'E_CORE_ERROR';
    case E_CORE_WARNING: // 32 //
      return 'E_CORE_WARNING';
    case E_COMPILE_ERROR: // 64 //
      return 'E_COMPILE_ERROR';
    case E_COMPILE_WARNING: // 128 //
      return 'E_COMPILE_WARNING';
    case E_USER_ERROR: // 256 //
      return 'E_USER_ERROR';
    case E_USER_WARNING: // 512 //
      return 'E_USER_WARNING';
    case E_USER_NOTICE: // 1024 //
      return 'E_USER_NOTICE';
    case E_STRICT: // 2048 //
      return 'E_STRICT';
    case E_RECOVERABLE_ERROR: // 4096 //
      return 'E_RECOVERABLE_ERROR';
    case E_DEPRECATED: // 8192 //
      return 'E_DEPRECATED';
    case E_USER_DEPRECATED: // 16384 //
      return 'E_USER_DEPRECATED';
  }
  return "";
}

register_shutdown_function ('err_handler');

  //Проверяем, подключен ли модуль mysqli
  echo "<p>Проверка на существование класса <b>mysqli</b>: ";
    echo (class_exists('mysqli') == TRUE) ? 'ok' : 'error';
  echo "</p>\n";

  //Проверяем, подключен ли модуль filter_vars
  echo "<p>Проверка на существование функции <b>filter_input</b>: ";
    echo (function_exists('filter_input') == TRUE) ? 'ok' : 'error';
  echo "</p>\n";

  //Значения глобальных переменных
  echo "<p>display_errors: "; var_dump (ini_get('display_errors')); echo "</p>\n";

  echo "<p>error_reporting: ".ini_get('error_reporting')."</p>\n";

  echo "<p>max_execution_time: ".ini_get('max_execution_time')."</p>\n";

  include "config.php";
  global $config;

  $s = new mysqli("localhost", "rejik_adm", "admin3741", "rejik");
  $s->query("Show slave status;");
  print_r($s);
  //Значения глобальных переменных
  echo "<p>display_errors: "; var_dump (ini_get('display_errors')); echo "</p>\n";

  echo "<p>error_reporting: ".ini_get('error_reporting')."</p>\n";

  echo "<p>max_execution_time: ".ini_get('max_execution_time')."</p>\n";
?>