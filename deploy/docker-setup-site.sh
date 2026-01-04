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
if [ $(grep error_reporting .htaccess |grep -v "#" |wc -l |xargs) -eq 0 ]; then
	echo 'php_value error_reporting 32767' >> .htaccess
fi

# Show errors in the browser for easier debugging inside Docker.
if [ $(grep display_errors .htaccess |grep -v "#" |wc -l |xargs) -eq 0 ]; then
	echo 'php_flag display_errors On' >> .htaccess
fi

cd ..

# This keeps Composer from balking at the permissions of the directory
git config --global --add safe.directory /var/www

# Install Composer dependencies
composer install

# Install Node
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.1/install.sh | bash
source ~/.bashrc
export NVM_DIR="$HOME/.nvm"
# Node 10 will run on this old release, while still supporting our libraries
nvm install 10

# Install Node dependencies
cd htdocs/js/vendor; yarn build; cd ../../..

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php

# Note: Test users are now loaded as part of the initial database.sql file,
# not via load-test-users.sh, to ensure consistency with the database initialization

# Set up Sphinx and start it
echo "START=yes" | tee /etc/default/sphinxsearch
cp deploy/sphinx.conf /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{PDO_SERVER}|db|g" /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{PDO_USERNAME}|ricsun|g" /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{PDO_PASSWORD}|password|g" /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{MYSQL_DATABASE}|richmondsunlight|g" /etc/sphinxsearch/sphinx.conf

# Build the index before starting searchd if none exists; otherwise rotate while it's running.
mkdir -p /var/lib/sphinxsearch/data
if [[ -f /var/lib/sphinxsearch/data/bills.sph ]]; then
    service sphinxsearch start
    indexer --all --rotate || indexer --all
else
    indexer --all
    service sphinxsearch start
fi
