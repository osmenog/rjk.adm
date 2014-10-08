<?php
	//Параметры БД САМС
	$config ['sams_db']	 = array ('proxy.bankom.omsk.su', 'sams', 'qwerty', 'squidctrl', 'utf8');
	//$config ['sams_db']	 = array ('localhost', 'root', '', 'squidctrl', 'utf8'); //Домашний
	
	//Если этот параметр существует, то при выыводи информации из БД САМС будет выполнятся преобразование кодировки
	//$config ['conv'] = array ('cp1252','utf-8');
	
	//Параметры БД Режика и Логов
	$config ['rejik_db'] = array ('localhost', 'root', '', 'rejik', 'utf8');
	$config ['log_db']   = array ('localhost', 'root', '', 'rejik', 'utf8');

	// Логировать действия админов
	$config ['admin_log']   = True;

	//Режим отладки
	$config ['debug_mode']   = False;

	//$config ['banlist_path'] = 'C:/';

	//Название корневой папки проекта. Используется при указании абсолютного адреса.
	$config ['proj_name']	= 'rejik2';

	$config ['urls_per_page'] = 250; //Количество ссылок на одной странице

	$config ['sync_enabled'] = true; //Синхронизация

	$config ['private_key'] = 'secret'; //Секретный ключ сервера
	$config ['server_UUID'] = '432f50b1-4ec8-11e4-a448-38607725a76e'; //UUID определяющий данный сервер
?>