#!/bin/bash

cd /var/www/

# What this image calls html, we call htdocs
if [ -f html ]; then
    rmdir html
    ln -s htdocs html
fi

# Set the include path.
echo 'php_value include_path ".:includes/"' >> .htaccess

# Have PHP report errors.
echo 'php_value error_reporting 2039' >> .htaccess

# Install Composer dependencies
composer install

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php
