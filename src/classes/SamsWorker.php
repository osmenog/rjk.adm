<?php
include_once "classes/Workers.php";

class proxy_worker{

  protected $sams_conn;

  public function __construct ($db_config) {
    $this->sams_conn = sams_connect::getInstance($db_config);
  }


  public function get_userscount() {
    $query_str = "SELECT Count(*) FROM squidusers\n";
    $res = $this->sams_conn->query($query_str);


    $row = $res->fetch_row();
    $res->close();
    return $row[0];
  }

  public function get_userinfo($nick) {
    //todo добавить описание phpdoc
    //$this->sql->set_charset("utf8");
    //$this->sql->set_charset($this->db_codepage); //Устанавливаем кодировку соединения с БД Самса

    $query_str = "SELECT * FROM squidusers WHERE `nick`='$nick';";
    $res = $this->sams_conn->query($query_str);

    if ($res->num_rows==0) return FALSE;

    return $res->fetch_assoc();
  }


  /**
   * Возвращает список пользователей БД SAMS.
   * @param int $verbose_mode Обьем извлекаемых данных. Может быть одним из:
   * FIELDS_FULL - все поля
   * FIELDS_ONLY_LOGINS - только логины
   * @return array|int|boolean Массив, содержащий пользователей
   * 0 - в случае, если в БД нет пользователей
   * False - в случае, если скрипт завершился с ошибкой
   */
  public function get_userslist($verbose_mode = FIELDS_FULL) {
    global $config;
    //Устанавливаем кодировку соединения с БД Самса
    //$this->sql->set_charset($this->db_codepage);

    //Устанавливаем, тип запроса, в зависимости от входных данных
    if ($verbose_mode == FIELDS_ONLY_LOGINS) {
      $query = "SELECT `nick` FROM squidusers";
    } elseif ($verbose_mode == FIELDS_FULL)  {
      $query = "SELECT * FROM squidusers";
    } else {
      return FALSE;
    }

    //Выполняем запрос к БД
    $response = $this->sams_conn->query($query);

    // Если в БД нет ни одной записьи - то возвращаем 0
    if ($response->num_rows == 0) return 0;

    $res = array();
    if ($verbose_mode == FIELDS_FULL) {
      while ($row = $response->fetch_assoc()) {
        if (isset($config['conv'])) {
          $row['family'] = empty($row["family"]) ? '' : iconv($config['conv'][0], $config['conv'][1], $row['family']);
          $row['name'] = empty($row["name"]) ? '' : iconv($config['conv'][0], $config['conv'][1], $row['name']);
          $row['soname'] = empty($row["soname"]) ? '' : iconv($config['conv'][0], $config['conv'][1], $row['soname']);
        }
        //$res[$row['nick']] = $row;
        $res[] = $row;
      }
    } elseif ($verbose_mode == FIELDS_ONLY_LOGINS) {
      //$row = $response->fetch_row();
      //var_dump ($row);
      while ($row = $response->fetch_row()) {
        $res[]=$row[0];
      }
    }

    $response->close();
    return $res;
  }

  /**
   * Функция проверяет, есть ли пользователь $nick в базе.
   * Возвращаяет True если пользователь есть, 0 - если польз. отсутствует и FALSE - если произошла ошибка
   * @param $nick
   * @return bool|0
   */
  public function is_user ($nick) {
    $response = $this->sams_conn->query("SELECT * FROM squidusers WHERE nick='$nick';");

    if ($response->num_rows == 0) {
      return 0;
    } else {
      return TRUE;
    }
  }

  public function get_groups() {
    $query = "SELECT `name`,`nick` FROM `groups`;";

    $response = $this->sams_conn->query($query);

    if ($response->num_rows == 0) return 0;

    $res = array();
    while ($row = $response->fetch_row()) {
      $res[] = $row;
    }

    $response->close();

    return $res;
  }

  public function sams_create_user ($user) {
//    $sams_uid = '';
//    $nick    = $user['login'];
//    $fio     = explode(" ", $user['name']);
//    $family  = $fio[0];
//    $name    = $fio[1];
//    $soname  = $fio[2];
//    $group   = $user['sams_group'];
//    $domain  = $user['sams_domain'];
//    $passwd  = $user['password'];
//    $shablon = $user['sams_shablon'];
//    $quotes  = $user['sams_quotes'];
//    $size    = $user['sams_size'];
//    $enabled = $user['sams_enabled'];
//    $ip      = $user['sams_ip'];
//    $mask    = $user['sams_ip_mask'];
//
//    $query = "INSERT INTO squidusers (user_id, assign_pid) VALUES('{$uid}', {$local_pid});";
//    $res = $this->rejik_conn->do_query($query);
//    //fixme Придумать код для сообщения
//    //if ($res) Logger::add(0,"Пользователь {$row['login']} (pid={$pid}) был привязан к прокси (pid={$local_pid})","",-1,"sams_sync");
//    //$row['assign_pid'] = $local_pid;
//    $linked_users[] = $row;
  }

  public function close_db() {
    if (isset($this->sams_conn)) {
      $this->sams_conn->close_db();
    }
  }

} //end of proxy worker

?>