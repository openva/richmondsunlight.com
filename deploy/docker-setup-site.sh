#!/bin/bash

cd /var/www/

# What this image calls html, we call htdocs
if [ -f html ]; then
    rmdir html
    ln -s htdocs html
fi

cd htdocs

# Set the include path.
if [ $(grep include_path .htaccess |grep -v "#" |wc -l |xargs) -eq 0 ]; then
	echo 'php_value include_path ".:includes/"' >> .htaccess
fi

# Have PHP report errors.
if [ $(grep 2039 .htaccess |grep -v "#" |wc -l |xargs) -eq 0 ]; then
	echo 'php_value error_reporting 2039' >> .htaccess
fi

cd ..

# Install Composer dependencies
composer install

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php
