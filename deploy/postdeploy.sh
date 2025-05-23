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

# Make the cache directories writeable
chmod o+w "$SITE_PATH"/htdocs/cache/
chmod o+w "$SITE_PATH"/htdocs/rss/cache/

# Set Memcached to start every time
sudo systemctl enable memcached

# Ensure that Memcached will listen to other RS servers.
sudo sed -i 's/-l 127.0.0.1/-l 0.0.0.0/' /etc/memcached.conf

# Set up Apache, if need be.
SITE_SET_UP="$(sudo apache2ctl -S 2>&1 |grep -c " $SITE_URL ")"
if [ "$SITE_SET_UP" -eq "0" ]; then

    # Set up Apache
    sudo cp deploy/virtualhost-"$SITE_URL".txt /etc/apache2/sites-available/"$SITE_URL".conf
    sudo a2ensite "$SITE_URL"
    sudo a2enmod headers expires rewrite http2 ssl
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

# If this is for production, then reindex the data and start Memcached
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

    if ! systemctl is-active --quiet memcached; then
        sudo systemctl start memcached
    fi
    
fi

# Populate the template with the list of legislators
php deploy/populate_menu.php

# Expire the cached template
echo "delete template-new" | nc -N localhost 11211  || true
