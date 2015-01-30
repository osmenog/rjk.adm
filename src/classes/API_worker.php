<?php
  include_once "classes/HealthPanel.php";
  include_once "classes/Classes.php";
  include_once "classes/Exceptions.php";

class api_worker {
  protected $rejik;
  private $verison;

  public function __construct (rejik_worker $rejik, $version) {
    $this->$version=$version;

    if (is_object($rejik) && get_class($rejik)=='rejik_worker'){
      $this->rejik = $rejik;
    } else {
      throw new api_exception("Error Processing Request", 1);
    }
  }

  public static function validate ($data) {
    //Функция проверяет входные данные
    //Сюда нужно добавить поддержку проверки схемы
    //echo "<pre>"; print_r ($data); echo "</pre>\n";

    //Проверка на наличие ключевого свойства
    //if (!isset($data['action']) or ($data['action']=='')) throw new api_exception ("Не указано свойство 'action'",2);
    //if (!isset($data['sig']) or ($data['sig']=='')) throw new api_exception ("Не указано свойство 'sig'",2);

    foreach ($data as $k => $v) {
      if ($k=='offset' or $k=='limit') {
        if (!ctype_digit($data[$k])) throw new api_exception ("Атрибут '{$k}' должен иметь числовое значение",2);
      } 
      if ($k=='banlist') {
        if ($v=='') throw new api_exception ("Не указано свойство '{$k}'",2);
      }  
    }
    return $data;
  }

  public function check_signature($data) {
    $sig = $data['sig'];
    unset($data['sig']);
    ksort($data);
    //echo "<pre>"; print_r ($data); echo "</pre>\n";
    $str_data='';
    foreach ($data as $k=>$v) $str_data.=$k."=".$v;
    
    $md5_data=md5($str_data);
    //echo "<pre>"; print_r ($str_data); echo "</pre>\n";
    if ($sig!=$md5_data) throw new api_exception ("Полученная сигнатура не совпадает с рассчитаной: [{$md5_data}]",3);
  }

  public function banlist_addurl($banlist, $url) {
    try {
      $rjk = $this->rejik;
      $result = $rjk->banlist_add_url ($banlist, $url);

      $json_obj = array ('id' => $result);
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_changeurl($banlist, $url_id, $url) {
    try {
      $rjk = $this->rejik;
      $rjk->banlist_change_url ($banlist, $url_id, $url);

      $json_obj = array ('result' => 1);
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_removeurl($banlist, $url_id) {
    try {
      $rjk = $this->rejik;
      $rjk->banlist_remove_url ($banlist, $url_id);
      $json_obj = array ('result' => 1);
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      return $json_str;
      
    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_getUrlListEx($banlist, $offset=0, $limit=10) {
    // Description ...: Функция должна вернуть JSON обьект, содержащий ссылки по заданному смещению
    // Parameters ....: $banlist - имя банлиста
    //                  $offset - смещение, относительно начала
    //                  $limit - количество возвращаемых ссылок
    // Return values .: Успех - Возвращает JSON обьект, содержащий:
    //                          "banlist" - имя банлиста
    //                          "limit" - общее количество ссылок в банлисте
    //                          "total" - сколько ссылок передано в теле JSON обьекта
    //                          "offset" - возвращает смещение
    //                          "urls" - содержит обьект-ассоциативный массив, содержащий: {[ид ссылки] => [ссылка], ... }
    //                        - Если банлист не содержит ссылок, то все-равно будет возвращен JSON обьект, указанный выше.
    //                          При этом будет: length=0 и urls = []
    //                  Неудача - Будет вызвано исключение api_exception
    // -------------------------------------------------------------------------
    try {
      $rjk = $this->rejik;

      if (!$rjk->is_banlist($banlist)) throw new api_exception ("Banlist not found",3);
      
      $urls = $rjk->banlist_get_urls ($banlist, $offset, $limit);
      //echo "{$offset} | {$limit}";
      $json_obj = array ('banlist'=>$banlist,
                         'limit'=>$limit,
                         'offset'=>$offset,
                         'total'=>0,
                         'urls'=>array());
  
      $urls_counter = 0; //Инициализируем счетчик для ссылок
      $urls_arr = array(); //Инициализируем массив для ссылок с ключами
      //Заполняем массив ссылками
      if ($urls!=0) {
        foreach ($urls as $key => $value) {
          $id= intval($value[0]);
          $url=$value[1];
          $urls_arr[$id]=$url;
          $urls_counter++;
        }
      }
      //$urls_arr = array('ya.ru/hi&a=1?b=2','<script>alert();</script>');
  
      $json_obj['urls']=$urls_arr;
      $json_obj['total']=$urls_counter;
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function banlist_searchurl($banlist, $query) {
    try {
      $rjk = $this->rejik;
      if (!$rjk->is_banlist($banlist)) {throw new api_exception ("Banlist not found",3);}

      $founded_urls = $rjk->banlist_search($banlist, $query);

      $json_obj = array ('total'=>0, 'urls'=>array());
  
      $urls_counter = 0; //Инициализируем счетчик для ссылок
      $urls_arr = array(); //Инициализируем массив для ссылок с ключами
      //Заполняем массив ссылками
      if ($founded_urls!=0) {
        foreach ($founded_urls as $key => $value) {
          $id= intval($key);
          $url=$value;
          $urls_arr[$id]=$url;
          $urls_counter++;
        }
      }

      $json_obj['urls']=$urls_arr;
      $json_obj['total']=$urls_counter;
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      
      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  public function log_get($start, $len) {
    try {
      Logger::init();
      $log = Logger::get($start, $len);

      $json_obj = array (  'limit' => $len,
                          'offset' => $start,
                           'total' => 0,
                          'events' => array());
      $events_counter = 0;
      $events_arr = array();

      if (!$log) {
        foreach ($log as $key => $value) {
          $id= intval($value[0]);
          $row=$value[1];
          $events_arr[$id]=$row;

          $events_counter++;
        }
      }
      
      $json_obj['events']=$events_arr;
      $json_obj['total']=$events_counter;
      
      $json_str = json_encode($json_obj, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);  
      
      Logger::stop();

      return $json_str;

    } catch (exception $e) {
      throw $e;
    }
  }

  /**
   * API функция, выполняющая проверку доступности серверов.
   * @return string Результат выполнения в JSON виде.
   */
  public function check_servers_availability () {
    try {
      CheckSession();

      //Инициализируем главный обьект
      $hp = new HealthPanel();

      //Вне зависимости от будующего результата, сохраняем в сессии пометку о выполненной проверке.
      $_SESSION['is_servers_checked'] = 1;

      //Выполняем проверку доступности серверов
      $hp->check_availability();

      //Сохраняем результаты проверки в открытой сессии
      $hp->save_session();
      
      return "{\"result\":1}";
    } catch (Exception $e) {
      throw $e;
    }
  }
} //enf of api_worker
?>