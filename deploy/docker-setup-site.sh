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

# Copy over the Sphinx configuration, start Sphinx
cp deploy/sphinx.conf /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{PDO_SERVER}|db|g" /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{PDO_USERNAME}|ricsun|g" /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{PDO_PASSWORD}|password|g" /etc/sphinxsearch/sphinx.conf
sed -i -e "s|{MYSQL_DATABASE}|richmondsunlight|g" /etc/sphinxsearch/sphinx.conf
/etc/init.d/sphinxsearch restart

# If we have an existing index, update it
if [[ -f /var/lib/sphinxsearch/data/bills.sph ]]; then

    # Reindex
    indexer --all --rotate

# If there is no index, create a new one
else
    indexer --all
fi
