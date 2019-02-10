#!/bin/bash

grep "ServerName localhost" /etc/apache2/apache2.conf
if [ $? -ne 0 ]; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

# If the php.ini doesn't exist, create it.
if [ ! -f "/usr/local/etc/php/php.ini" ]; then
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
    echo "extension=memcached.so" >> /usr/local/etc/php/php.ini
fi

