<?php
// -------------------------------------------------------------
//Настройки соединения для текущего сервера
// -------------------------------------------------------------

  //Параметры БД САМС
  $config  ['sams_db'] = array('localhost', 'sams', 'qwerty', 'squidctrl', 'utf8');
  //Параметры БД Режика
  $config ['rejik_db'] = array('localhost', 'rejik_adm', 'admin3741', 'rejik', 'utf8');

// -------------------------------------------------------------
//Вспомогательные опции
// -------------------------------------------------------------

  // Логировать действия админов
  $config['admin_log'] = True;

  //Режим отладки
  $config['debug_mode'] = True;

  //Как будет обновляться статус серверов
  $config['use_check_cache'] = False;

  //Название корневой папки проекта. Используется при указании абсолютного адреса.
  $config['proj_name'] = 'adm';

  //Идентификатор и название группы, в которую будут перемещаться удаленные пользователи
  $config['trash_group_id'] = '51592397f28d5';
  $config['trash_group_default_name'] = 'removed';

  //Количество ссылок на одной странице
  $config['urls_per_page'] = 250;

  //Секретный ключ сервера
  $config['private_key'] = 'secret';

  //ID этого сервера. Такой же как и в конфиге MySQL
  $config['server_id']        = 3;
  $config['repl_user_name']   = 'repl_user';
  $config['repl_user_passwd'] = 'repl_user';

  $config['servers'] = array(
    'proxy2.bankom.omsk.su' => array($config['repl_user_name'], $config['repl_user_passwd'], 1),
    //'FreeBSD_2' => array ('repl_user', 'repl_pasword', 3)
  );

  //Путь до папки, где будут хранится всякие логи.
  $config['log_dir'] = '/var/log/rejik.adm/';

  //Если этот параметр существует, то при выыводи информации из БД САМС будет выполнятся преобразование кодировки
  //$config ['conv'] = array ('cp1252','cp1251');
?>