<?php
// -----------------------------------------------------------------------------------------------------------------------------------------------
class rejik_exception extends Exception {
  function get_json(){
    //Функция возвращает JSON обьект содержащий параметры ошибки
    $obj = array('error' => array(
                          'error_type'  => get_class($this),
                          'error_code'  => $this->getCode(),
                          'error_msg'   => $this->getMessage(),
                          'error_trace' => $this->getTraceAsString()));
    return json_encode($obj);
  }
}
class mysql_exception extends rejik_exception {
  private $sql_query="";
}
class api_exception extends rejik_exception {}
// -----------------------------------------------------------------------------------------------------------------------------------------------
?>