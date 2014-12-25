<?php
  
  include_once $_SERVER['DOCUMENT_ROOT']."/adm"."/config.php";
  include_once $_SERVER['DOCUMENT_ROOT']."/adm/classes"."/Logger.php";
  include_once $_SERVER['DOCUMENT_ROOT']."/adm/classes"."/Classes.php";
  global $config;
	
	$checker = Checker::getInstance();
	$checker->start(true);

	
?>