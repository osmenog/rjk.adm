<?php
	//Параметры БД САМС
	//$config ['sams_db']	 = array ('proxy.bankom.omsk.su', 'sams', 'qwerty', 'squidctrl', 'utf8'); //Боевой
	$config ['sams_db']	 = array ('localhost', 'root', '', 'squidctrl', 'utf8'); //Домашний
	
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

	//Привести код в нормальный вид:
	// 1. В случае ошибки функции должны возвращать одинаковые сообщения об ошибках. Где указывается имя вызвавшей ошибку функции. 
	// 2. Фсе функции кроме булевых должны возвращать FALSE в случае ошибки и выкидывать соответствующее исключение.
	// 3. Группировать код по обьектам, над которыми выполняются операции.
	// 3.1. Переименовать функции.
	// 
?>