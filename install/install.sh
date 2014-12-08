chown -R www:www /usr/local/www/apache24/data/rjk
chmod 777 /usr/local/www/apache24/data/rjk/

find /usr/local/www/apache24/data/rjk/ -type f -exec chmod 666 {} \;
find /usr/local/www/apache24/data/rjk/ -type d -exec chmod 777 {} \;

chmod 755 /usr/local/www/apache24/data/rjk/install.sh