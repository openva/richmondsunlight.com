#!/bin/bash

# Set variables based on whether this is for the staging site or the production site.
if [ "$DEPLOYMENT_GROUP_NAME" == "RS-Web-Staging" ]
then
    SITE_PATH=/var/www/staging.richmondsunlight.com
    SITE_URL=staging.richmondsunlight.com
elif [ "$DEPLOYMENT_GROUP_NAME" == "RS-Web-Fleet" ]
then
    SITE_PATH=/var/www/richmondsunlight.com
    SITE_URL=richmondsunlight.com
else
    echo "Fatal error: No deployment group found"
    exit 1
fi

# Set permissions properly, since appspec.yml gets this wrong.
chown -R ubuntu:ubuntu "$SITE_PATH"
chmod -R g+w "$SITE_PATH"
chown -R www-data:www-data "$SITE_PATH"/htdocs/matomo
chmod -R 777 "$SITE_PATH"/htdocs/matomo/tmp/

# Set up Matomo, if need be.
if [ ! -d "$SITE_PATH"/htdocs/matomo ]; then
    cd /tmp || exit
    wget https://builds.matomo.org/piwik.zip
    unzip piwik.zip
    sudo cp -r piwik "$SITE_PATH"/htdocs/matomo
    sudo chown -R www-data:www-data "$SITE_PATH"/htdocs/matomo
    sudo chmod -R 755 "$SITE_PATH"/htdocs/matomo
fi

# Set up Apache, if need be.
SITE_SET_UP="$(sudo apache2ctl -S 2>&1 |grep -c " $SITE_URL ")"
if [ "$SITE_SET_UP" -eq "0" ]; then

    # Set up Apache
    sudo cp deploy/virtualhost-"$SITE_URL".txt /etc/apache2/sites-available/"$SITE_URL".conf
    sudo a2ensite "$SITE_URL"
    sudo a2enmod headers expires rewrite http2 ssl
    sudo a2enconf php5.6-fpm
    sudo systemctl reload apache2

    # Install a certificate
    sudo certbot --apache -d "$SITE_URL" --non-interactive --agree-tos --email jaquith@gmail.com --redirect

    # Create the cache directories, make them writable
    mkdir -p "$SITE_PATH"/htdocs/cache
    sudo chgrp www-data "$SITE_PATH"/htdocs/cache
    sudo chmod g+w -R "$SITE_PATH"/htdocs/cache

    mkdir -p "$SITE_PATH"/htdocs/rss/cache
    sudo chgrp www-data "$SITE_PATH"/htdocs/rss/cache
    sudo chmod g+w -R "$SITE_PATH"/htdocs/rss/cache
    
    mkdir -p "$SITE_PATH"/htdocs/photosynthesis/rss/cache
    sudo chgrp www-data "$SITE_PATH"/htdocs/photosynthesis/rss/cache
    sudo chmod g+w -R "$SITE_PATH"/htdocs/photosynthesis/rss/cache

fi

# If this is for production, then reindex the data.
if [ "$DEPLOYMENT_GROUP_NAME" == "RS-Web-Fleet" ]
then
    # Copy over the Sphinx configuration, restart Sphinx
    sudo cp deploy/sphinx.conf /etc/sphinxsearch/sphinx.conf
    sudo /etc/init.d/sphinxsearch restart
    
    # If we have an existing index, update it
    if [[ -f /var/lib/sphinxsearch/data/bills.sph ]]; then
        # Reindex, continuing after logout, because it takes ~40 minutes to run
        nohup sudo indexer --all --rotate > /dev/null 2>&1 &
    # If there is no index, create a new one
    else
        nohup sudo indexer --all > /dev/null 2>&1 &
    fi
    
fi

# Expire the cached template (in case we've made changes to it)
echo "delete template-new" | nc -N localhost 11211  || true
