--
-- Скрипт сгенерирован Devart dbForge Studio for MySQL, Версия 6.2.280.0
-- Домашняя страница продукта: http://www.devart.com/ru/dbforge/mysql/studio
-- Дата скрипта: 07.10.14 17:52:40
-- Версия сервера: 5.6.16
-- Версия клиента: 4.1
--


USE rejik;

DROP TABLE IF EXISTS banlists;
CREATE TABLE IF NOT EXISTS banlists (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(25) binary NOT NULL,
  short_desc text binary DEFAULT NULL,
  full_desc text binary DEFAULT NULL,
  crc varbinary(20) NOT NULL,
  users_crc varbinary(20) NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 7
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
AUTO_INCREMENT = 10
AVG_ROW_LENGTH = 1820
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
AUTO_INCREMENT = 81
AVG_ROW_LENGTH = 204
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS syncronize_url;
CREATE TABLE IF NOT EXISTS syncronize_url (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  banlist_name varchar(50) binary NOT NULL,
  url varchar(255) binary DEFAULT NULL,
  url_id int(11) DEFAULT NULL,
  action int(11) DEFAULT NULL,
  action_time datetime DEFAULT NULL,
  sync_time datetime DEFAULT NULL,
  sync_result int(11) DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 3
AVG_ROW_LENGTH = 8192
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS urls;
CREATE TABLE IF NOT EXISTS urls (
  id int(11) NOT NULL AUTO_INCREMENT,
  url varchar(255) binary NOT NULL,
  banlist varchar(30) binary NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 466092
AVG_ROW_LENGTH = 51
CHARACTER SET utf8
COLLATE utf8_bin;

DROP TABLE IF EXISTS users_acl;
CREATE TABLE IF NOT EXISTS users_acl (
  id int(11) NOT NULL AUTO_INCREMENT,
  nick varchar(25) binary NOT NULL,
  banlist_id int(11) NOT NULL,
  banlist varchar(25) binary NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB
AUTO_INCREMENT = 58
AVG_ROW_LENGTH = 585
CHARACTER SET utf8
COLLATE utf8_bin;