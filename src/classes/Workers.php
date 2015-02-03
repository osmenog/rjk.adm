<?php
include_once "classes/db_connectors.php";

class master_connect extends mysql_connection {

}
class slave_connect extends mysql_connection {

}

class worker {

  protected $master;
  protected $slave;

  public function __construct($slave_config, $master_config = FALSE) {
    //Получаем соединение только для чтения
    $this->slave = slave_connect::getInstance($slave_config);

    if ($master_config !== FALSE && is_array($master_config)) {
      $this->master = master_connect::getInstance($master_config);
    }
  }

  public function close_db() {
    if (isset($this->slave)) {
      $this->slave->close_db();
    }
    if (isset($this->master)) {
      $this->master->close_db();
    }
  }
}

?>