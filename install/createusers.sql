﻿CREATE USER 'repl_user'@'%';
GRANT REPLICATION CLIENT, REPLICATION SLAVE, SUPER ON *.* TO 'repl_user'@'%';
SET PASSWORD FOR 'repl_user'@'%' = PASSWORD ('12341234');