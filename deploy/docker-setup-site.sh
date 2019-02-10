#!/bin/bash

cd /var/www/

# What this image calls html, we call htdocs
if [ -f html ]; then
    rmdir html
    ln -s htdocs html
fi

# Install Composer dependencies
composer install

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php
