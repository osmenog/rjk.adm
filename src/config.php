<?php
// -------------------------------------------------------------
//Настройки соединения для текущего сервера
// -------------------------------------------------------------

  //ID этого сервера. Такой же как и в конфиге MySQL
  $config['server_id']        = 6;

  $config['repl_user_name']   = 'repl_user';
  $config['repl_user_passwd'] = '12341234';

  $config['db_user_name']     = 'rejik_adm';
  $config['db_user_pass']     = '43214321';

  //Параметры БД САМС
  $config  ['sams_db'] = array('localhost', 'sams', 'qwerty', 'squidctrl', 'utf8');
  //Параметры БД Режика
  $config ['rejik_db'] = array('localhost', $config['db_user_name'], $config['db_user_pass'], 'rejik', 'utf8');

// -------------------------------------------------------------
//Вспомогательные опции
// -------------------------------------------------------------

  // Логировать действия админов
  $config['admin_log'] = True;

  //Режим отладки
  $config['debug_mode'] = True;
  $config['user_sync_extended_log'] = False;

  //Как будет обновляться статус серверов
  $config['use_check_cache'] = True;

  //Название корневой папки проекта. Используется при указании абсолютного адреса.
  $config['proj_name'] = 'adm';

  //Идентификатор и название группы, в которую будут перемещаться удаленные пользователи
  $config['trash_group_id'] = '51592397f28d5';
  $config['trash_group_default_name'] = 'removed';
  $config['cut_group_name'] = True;


  //Количество ссылок на одной странице
  $config['urls_per_page'] = 250;

  //Секретный ключ сервера
  $config['private_key'] = 'secret';

  $config['servers'] = array(
    'proxy2.bankom.omsk.su' => array($config['repl_user_name'], $config['repl_user_passwd'], 1),
    //'proxy6.bankom.omsk.su' => array($config['repl_user_name'], $config['repl_user_passwd'], 6),
    'oib01'                 => array($config['repl_user_name'], $config['repl_user_passwd'], 3),
    'FreeBSD_01'            => array($config['repl_user_name'], $config['repl_user_passwd'], 11),
    'FreeBSD_02'            => array($config['repl_user_name'], $config['repl_user_passwd'], 12),
    'osme-n'                => array($config['repl_user_name'], $config['repl_user_passwd'], 13)
  );

  //Путь до папки, где будут хранится всякие логи.
  $config['log_dir'] = '/var/log/rejik.adm/';

  //$config['tmp_dir_path'] = '/tmp/';
  $config['tmp_dir_path'] = 'F:\\MyFiles\\xampp\\tmp\\';

  //Если этот параметр существует, то при выыводи информации из БД САМС будет выполнятся преобразование кодировки
  //$config ['conv'] = array ('cp1252','cp1251');
?>