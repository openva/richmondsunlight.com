#!/bin/bash

cd /var/www/

find .

# What this image calls html, we call htdocs
if [ ! -f htdocs ]; then
    ln -s html htdocs
fi

# Install Composer dependencies
cd htdocs
composer install
cd ..

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php
