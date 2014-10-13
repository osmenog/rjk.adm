<?php
// -----------------------------------------------------------------------------------------------------------------------------------------------
class rejik_exception extends Exception {
  function get_json(){
    //Функция возвращает JSON обьект содержащий параметры ошибки
    $obj = exception_get_json ($this->getCode(), $this->getMessage(), get_class($this), $this->getTraceAsString());
    return $obj;
  }
}

class mysql_exception extends rejik_exception {
  private $sql_query="";
}
class api_exception extends rejik_exception {}

function exception_get_json($error_code, $error_msg, $error_type='', $error_trace='') {
  $obj = array('error' => array(
                          'error_code'  => $error_code,
                          'error_msg'   => $error_msg
                          )
              );

  if (!empty ($error_type)  ) $obj['error'][ 'error_type'  ] = $error_type;
  if (!empty ($error_trace) ) $obj['error'][ 'error_trace' ] = $error_trace;

  return json_encode($obj);
}
// -----------------------------------------------------------------------------------------------------------------------------------------------
?>