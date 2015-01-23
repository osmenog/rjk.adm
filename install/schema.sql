--
-- Скрипт сгенерирован Devart dbForge Studio for MySQL, Версия 6.2.280.0
-- Домашняя страница продукта: http://www.devart.com/ru/dbforge/mysql/studio
-- Дата скрипта: 23.01.15 18:02:35
-- Версия сервера: 5.6.21-log
-- Версия клиента: 4.1
--


USE rejik;

DROP TABLE IF EXISTS banlists;
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
AUTO_INCREMENT = 1
AVG_ROW_LENGTH = 2730
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS checker;
CREATE TABLE IF NOT EXISTS checker (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  file varchar(255) binary NOT NULL,
  lastcheck datetime NOT NULL,
  msg varchar(255) binary DEFAULT NULL,
  diff varchar(1024) binary DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1
AVG_ROW_LENGTH = 2048
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS log;
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
AUTO_INCREMENT = 3047
AVG_ROW_LENGTH = 280
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS servers;
CREATE TABLE IF NOT EXISTS servers (
  sql_id int(11) NOT NULL,
  hostname varchar(255) NOT NULL,
  visible_name varchar(50) DEFAULT NULL,
  description varchar(255) DEFAULT NULL,
  last_users_sync datetime DEFAULT NULL,
  PRIMARY KEY (sql_id)
)
ENGINE = INNODB
CHARACTER SET utf8
COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS urls;
CREATE TABLE IF NOT EXISTS urls (
  id int(11) NOT NULL AUTO_INCREMENT,
  url varchar(255) binary NOT NULL,
  banlist varchar(30) binary NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 932183
AVG_ROW_LENGTH = 49
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS users;
CREATE TABLE IF NOT EXISTS users (
  id int(11) NOT NULL AUTO_INCREMENT,
  login varchar(25) NOT NULL,
  proxy_id tinyint(4) NOT NULL,
  name varchar(70) DEFAULT NULL,
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
AUTO_INCREMENT = 616
AVG_ROW_LENGTH = 4096
CHARACTER SET utf8
COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS users_acl;
CREATE TABLE IF NOT EXISTS users_acl (
  id int(11) NOT NULL AUTO_INCREMENT,
  nick varchar(25) binary NOT NULL,
  banlist varchar(25) binary NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1255
AVG_ROW_LENGTH = 546
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS users_linked;
CREATE TABLE IF NOT EXISTS users_linked (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  assign_pid int(11) NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 1213
CHARACTER SET utf8
COLLATE utf8_unicode_ci;

DROP TABLE IF EXISTS variables;
CREATE TABLE IF NOT EXISTS variables (
  name varchar(50) NOT NULL,
  value varchar(255) NOT NULL,
  PRIMARY KEY (name)
)
ENGINE = INNODB
AVG_ROW_LENGTH = 16384
CHARACTER SET utf8
COLLATE utf8_unicode_ci;

DROP VIEW IF EXISTS view1 CASCADE;
CREATE OR REPLACE
DEFINER = 'root'@'192.168.139.29'
VIEW view1
AS
SELECT
  `ul`.`id` AS `id`,
  `u`.`login` AS `login`,
  `ul`.`assign_pid` AS `assign_pid`
FROM (`users_linked` `ul`
  JOIN `users` `u`
    ON ((`ul`.`user_id` = `u`.`id`)));