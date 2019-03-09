#!/bin/bash

# Make localhost the name of the host
grep "ServerName localhost" ls /etc/apache2/sites-available/000-default.conf
if [ $? -ne 0 ]; then
    sed -i 's/#ServerName www.example.com/ServerName localhost/g' /etc/apache2/sites-available/000-default.conf
fi

# Make /var/www/htdocs the webroot
grep "htdocs" /etc/apache2/sites-available/000-default.conf
if [ $? -ne 0 ]; then
    sed -i 's/html/htdocs/g' /etc/apache2/sites-available/000-default.conf
fi

# If the php.ini doesn't exist, create it.
if [ ! -f "/usr/local/etc/php/php.ini" ]; then
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
    #echo "extension=memcached.so" >> /usr/local/etc/php/php.ini
fi
