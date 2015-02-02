<?php
include_once "classes/db_connectors.php";

class master_connect extends mysql_connection {

}
class slave_connect extends mysql_connection {

}

class worker {

  protected $master;
  protected $slave;

  public function __construct($db_config) {
    $this->slave = slave_connect::getInstance($db_config);

    try {
      $config['db_user_name']     = 'rejik_adm';
      $config['db_user_pass']     = '43214321';
      $this->master = master_connect::getInstance(array('oib01', $config['db_user_name'], $config['db_user_pass'], 'rejik', 'utf8'));
    } catch (Exception $e) {
      //echo "[m] ".$e->getMessage();
      $this->master = & $this->slave;
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