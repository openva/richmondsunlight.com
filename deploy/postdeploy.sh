#!/bin/bash

# Set permissions properly, since appspec.yml gets this wrong.
chown -R ubuntu:ubuntu /var/www/richmondsunlight.com/
chmod -R g+w /var/www/richmondsunlight.com/

# Set up Apache, if need be.
SITE_SET_UP="$(sudo apache2ctl -S 2>&1 |grep -c richmondsunlight.com)"
if [ "$SITE_SET_UP" -eq "0" ]; then

    # Set up Apache
    sudo cp deploy/virtualhost.txt /etc/apache2/sites-available/richmondsunlight.com.conf
    sudo a2ensite richmondsunlight.com
    sudo a2enmod headers expires rewrite http2
    sudo systemctl reload apache2

    # Install a certificate
    sudo certbot --apache -d richmondsunlight.com --non-interactive --agree-tos --email jaquith@gmail.com --redirect

    # Set the cache directory
    mkdir -p /var/www/richmondsunlight.com/htdocs/cache
    sudo chgrp www-data /var/www/richmondsunlight.com/htdocs/cache

fi

# Copy over the Sphinx configuration, restart Sphinx
sudo cp deploy/sphinx.conf /etc/sphinxsearch/sphinx.conf
sudo /etc/init.d/sphinxsearch restart

# Index the database
sudo indexer --all --rotate

# Expire the cached template (in case we've made changes to it)
echo "delete template-new" | nc localhost 11211  || true
