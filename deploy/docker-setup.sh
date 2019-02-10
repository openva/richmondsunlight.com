#!/bin/bash

# What this image calls html, we call htdocs
ln -s html htdocs

echo "ServerName localhost" >> /etc/apache2/apache2.conf
echo "extension=memcached.so" >> /usr/local/etc/php/php.ini

# Install Composer dependencies
find .
cd htdocs
composer install
cd ..

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php

/usr/sbin/apache2ctl -D FOREGROUND