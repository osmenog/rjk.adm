DROP USER 'repl_user';
DROP USER 'rejik_adm';

CREATE USER 'repl_user'@'%';
GRANT REPLICATION CLIENT, REPLICATION SLAVE, SUPER, RELOAD ON *.* TO 'repl_user'@'%';
SET PASSWORD FOR 'repl_user'@'%' = PASSWORD ('12341234');

CREATE USER 'rejik_adm'@'%';
GRANT ALL ON `rejik`.* TO 'rejik_adm'@'%';
SET PASSWORD FOR 'rejik_adm'@'%' = PASSWORD ('43214321');

FLUSH PRIVILEGES;