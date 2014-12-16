﻿--
-- Скрипт сгенерирован Devart dbForge Studio for MySQL, Версия 6.2.280.0
-- Домашняя страница продукта: http://www.devart.com/ru/dbforge/mysql/studio
-- Дата скрипта: 16.12.14 18:52:39
-- Версия сервера: 5.6.21-log
-- Версия клиента: 4.1
--


DROP DATABASE IF EXISTS rejik;
CREATE DATABASE IF NOT EXISTS rejik
CHARACTER SET utf8
COLLATE utf8_unicode_ci;

USE rejik;

CREATE TABLE IF NOT EXISTS banlists (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(25) binary NOT NULL,
  short_desc text binary DEFAULT NULL,
  full_desc text binary DEFAULT NULL,
  crc varbinary(20) DEFAULT NULL,
  users_crc varbinary(20) DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 35
AVG_ROW_LENGTH = 2730
CHARACTER SET utf8
COLLATE utf8_bin;

CREATE TABLE IF NOT EXISTS checker (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  file varchar(255) binary NOT NULL,
  lastcheck datetime NOT NULL,
  msg varchar(255) binary DEFAULT NULL,
  diff varchar(1024) binary DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 157
AVG_ROW_LENGTH = 2048
CHARACTER SET utf8
COLLATE utf8_bin;

CREATE TABLE IF NOT EXISTS log (
  id int(11) NOT NULL AUTO_INCREMENT,
  datentime datetime NOT NULL,
  code tinyint(4) NOT NULL,
  message varchar(255) binary DEFAULT NULL,
  attribute varchar(255) binary DEFAULT NULL,
  user_login varchar(25) binary NOT NULL,
  user_ip varchar(15) binary NOT NULL,
  crc varbinary(5) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE INDEX id (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 458
AVG_ROW_LENGTH = 280
CHARACTER SET utf8
COLLATE utf8_bin;

CREATE TABLE IF NOT EXISTS urls (
  id int(11) NOT NULL AUTO_INCREMENT,
  url varchar(255) binary NOT NULL,
  banlist varchar(30) binary NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 983026
AVG_ROW_LENGTH = 49
CHARACTER SET utf8
COLLATE utf8_bin;

CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT,
  login varchar(25) NOT NULL,
  proxy_id tinyint(4) NOT NULL,
  name varchar(25) DEFAULT NULL,
  password varchar(25) DEFAULT NULL,
  sams_group varchar(25) DEFAULT NULL,
  sams_domain varchar(25) DEFAULT NULL,
  sams_shablon varchar(25) DEFAULT NULL,
  sams_quotes bigint(20) DEFAULT 1,
  sams_size bigint(20) DEFAULT 0,
  sams_enabled int(11) DEFAULT 0,
  sams_ip char(15) DEFAULT NULL,
  sams_ip_mask char(25) DEFAULT NULL,
  sams_flags varchar(10) DEFAULT '0',
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 13
AVG_ROW_LENGTH = 4096
CHARACTER SET utf8
COLLATE utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS users_acl (
  id int(11) NOT NULL AUTO_INCREMENT,
  nick varchar(25) binary NOT NULL,
  banlist_id int(11) NOT NULL,
  banlist varchar(25) binary NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 2048
AVG_ROW_LENGTH = 546
CHARACTER SET utf8
COLLATE utf8_bin;

CREATE TABLE IF NOT EXISTS users_location (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  assign_pid int(11) NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_unicode_ci;