<?php
class ServersList implements Iterator {
  private $position = 0;            //Текущая позиция
  private $servers = array();       //Контейнер элементов
  private $servers_id = array();    //Массив, хранящий ID серверов

  public function __construct($servers_config) {
    if (is_array($servers_config)) {
      foreach ($servers_config as $key => $value) {
        $this->servers[] = new RejikServer ($key, $value[0], $value[1], $value[2]);
        $this->servers_id[] = $value[2];
      }
    }
  }
 
  public function current() {
    return $this->servers[$this->position];
  }
 
  public function next() {
    ++$this->position;
  }
 
  public function valid() {
    return isset($this->servers[$this->position]);
  }
 
  public function key() {
    return $this->position;
  }
 
  public function rewind() {
    $this->position = 0;
  }

  public function get_server_by_id ($id) {
    //Функция врзвращает обьект сервера по заданному ID.
    //Если по такому ID ничего не найдено, то возвращает false.
    
    //Перебираем массив, сопоставляющий индекс сервера в массиве servers и массив с ID серверов
    for ($i=0; $i <= (count($this->servers_id))-1; $i++) {
      if ($this->servers_id[$i] == $id) return $this->servers[$i];
    }
    return False;
  }
}

?>