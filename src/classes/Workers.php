<?php
include_once "classes/db_connectors.php";

class master_connect extends mysql_connection {

}
class slave_connect extends mysql_connection {

}

class worker2 {

  protected $master;
  protected $slave;

  public function __construct($db_config) {
    $this->slave = slave_connect::getInstance($db_config);

    try {
      $this->master = master_connect::getInstance($db_config);
    } catch (Exception $e) {
      echo "[m] ".$e->getMessage();
      $this->master = & $this->slave;
    }

  }

  public function __destruct() {
    if (isset($this->slave)) {
      $this->slave->close_db();
    }
    if (isset($this->master)) {
      $this->master->close_db();
    }
  }
}

?>