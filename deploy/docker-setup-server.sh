#!/bin/bash

# Make localhost the name of the host
grep "ServerName localhost" /etc/apache2/apache2.conf
if [ $? -ne 0 ]; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

# Make /var/www/htdocs the webroot
grep "html" /etc/apache2/apache2.conf
if [ $? -ne 0 ]; then
    sed -i 's/html/htdocs/g' 000-default.conf
fi

# If the php.ini doesn't exist, create it.
if [ ! -f "/usr/local/etc/php/php.ini" ]; then
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
    echo "extension=memcached.so" >> /usr/local/etc/php/php.ini
fi
