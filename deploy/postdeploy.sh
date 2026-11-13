#!/bin/bash

# Log actions to a file, because CodeDeploy logging isn't detailed enough.
LOGFILE=/tmp/postdeploy.log
exec > >(tee -a "$LOGFILE") 2>&1
PS4='+[$(date -Iseconds)] ${BASH_SOURCE##*/}:${LINENO}: '
set -x
echo "$(date -Iseconds) postdeploy starting; DEPLOYMENT_GROUP_NAME='${DEPLOYMENT_GROUP_NAME:-}'"

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
    echo "Fatal error: No deployment group found" | tee -a "$LOGFILE"
    exit 1
fi

# Start by making everything owned by ubuntu:www-data
sudo chown -R ubuntu:www-data "$SITE_PATH"/htdocs/
sudo chmod -R g+w "$SITE_PATH"/htdocs/

# Files populated with secrets at deploy time must not be world-readable (the
# appspec sets everything to 755)
sudo chmod 640 "$SITE_PATH"/htdocs/includes/settings.inc.php
sudo chmod o-rwx "$SITE_PATH"/deploy/sphinx.conf "$SITE_PATH"/deploy/database_export.sh \
    "$SITE_PATH"/deploy/postfix/sasl_passwd

# Make sure the cache directories exist and are world-writeable
mkdir -p "$SITE_PATH"/htdocs/cache/
mkdir -p "$SITE_PATH"/htdocs/rss/cache/
sudo chmod o+w "$SITE_PATH"/htdocs/cache/
sudo chmod o+w "$SITE_PATH"/htdocs/rss/cache/

# Set Memcached to start every time
sudo systemctl enable memcached

# Ensure that Memcached will listen to other RS servers.
sudo sed -i 's/-l 127.0.0.1/-l 0.0.0.0/' /etc/memcached.conf

# Keep Memcached's contents in a file, so that they (sessions, above all) survive restarts and
# reboots. See deploy/systemd/memcached-warm-restart.conf.
sudo install -d -o memcache -g memcache /var/lib/memcached
MEMCACHED_RESTART=0
if ! grep -q '^-e ' /etc/memcached.conf; then
    echo '-e /var/lib/memcached/memory_file' | sudo tee -a /etc/memcached.conf > /dev/null
    MEMCACHED_RESTART=1
fi
sudo mkdir -p /etc/systemd/system/memcached.service.d
if ! cmp -s "$SITE_PATH"/deploy/systemd/memcached-warm-restart.conf /etc/systemd/system/memcached.service.d/warm-restart.conf; then
    sudo cp "$SITE_PATH"/deploy/systemd/memcached-warm-restart.conf /etc/systemd/system/memcached.service.d/warm-restart.conf
    sudo systemctl daemon-reload
fi
# Memcached reads -e only when it starts. This first restart starts it empty (there's no saved
# file yet); every one after that keeps its contents.
if [ "$MEMCACHED_RESTART" -eq 1 ] && systemctl is-active --quiet memcached; then
    sudo systemctl restart memcached
fi

# Set up Apache, if need be.
SITE_SET_UP="$(sudo apache2ctl -S 2>&1 |grep -c " $SITE_URL ")"
if [ "$SITE_SET_UP" -eq "0" ]; then

    # Set up Apache
    sudo cp deploy/virtualhost-"$SITE_URL".txt /etc/apache2/sites-available/"$SITE_URL".conf
    sudo a2ensite "$SITE_URL"
    sudo a2enmod headers expires rewrite http2 ssl remoteip
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
    sudo cp "$SITE_PATH"/deploy/sphinx.conf /etc/sphinxsearch/sphinx.conf
    if getent group sphinxsearch > /dev/null; then
        sudo chown root:sphinxsearch /etc/sphinxsearch/sphinx.conf
        sudo chmod 640 /etc/sphinxsearch/sphinx.conf
    fi
    # Sphinx and the indexer are started through systemd, not directly from this script. Anything
    # this script starts belongs to codedeploy-agent, as far as systemd is concerned, so when the
    # OOM killer killed Sphinx or the indexer, systemd stopped codedeploy-agent too, and every
    # deployment after that failed.
    sudo systemctl restart sphinxsearch

    # Reindex nightly, rather than on every deployment.
    sudo cp "$SITE_PATH"/deploy/systemd/sphinx-reindex.service "$SITE_PATH"/deploy/systemd/sphinx-reindex.timer \
        /etc/systemd/system/
    sudo systemctl daemon-reload
    sudo systemctl enable --now sphinx-reindex.timer

    # But if there's no index at all (a new server), build one now, without waiting for it.
    if [[ ! -f /var/lib/sphinxsearch/data/bills.sph ]]; then
        sudo systemctl start --no-block sphinx-reindex.service
    fi

    if ! systemctl is-active --quiet memcached; then
        sudo systemctl start memcached
    fi
    
fi

# Populate the template with the list of legislators
php "$SITE_PATH"/deploy/generate_menu.php > "$SITE_PATH"/htdocs/includes/templates/legislators.html

# Report missing legislator photos
if [ "$DEPLOYMENT_GROUP_NAME" == "RS-Web-Fleet" ]
then
    php "$SITE_PATH"/deploy/photo-check.php
fi

# Expire the cached template
echo "delete template-new" | nc -N localhost 11211  || true

# Regenerate sitemaps, but only in production
if [ "$DEPLOYMENT_GROUP_NAME" == "RS-Web-Fleet" ]
then
    php "$SITE_PATH"/deploy/generate_sitemaps.php
fi

# Instruct web crawlers to avoid the staging site
if [ "$DEPLOYMENT_GROUP_NAME" == "RS-Web-Staging" ]
then
    cp "$SITE_PATH"/deploy/staging-robots.txt "$SITE_PATH"/htdocs/robots.txt
fi
