#!/bin/bash

# Set permissions properly, since appspec.yml gets this wrong.
chown -R ricsun:web /vol/www/richmondsunlight.com/
chmod -R g+w /vol/www/richmondsunlight.com/

# Set up Apache, if need be.
SITE_SET_UP="$(sudo apache2ctl -S |grep richmondsunlight.com |wc -l)"
if [ "SITE_SET_UP" -eq "0" ]; then

    # Set up Apache
    sudo cp deploy/virtualhost.txt /etc/apache2/sites-available/richmondsunlight.com.conf
    sudo a2ensite richmondsunlight.com
    sudo a2enmod headers expires rewrite http2
    sudo systemctl reload apache2

    # Install a certificate
    sudo certbot --apache -d richmondsunlight.com --non-interactive --agree-tos --email jaquith@gmail.com --redirect

    # Set the cache directory
    mkdir -p /vol/www/richmondsunlight.com/htdocs/cache
    sudo chgrp www-data /vol/www/richmondsunlight.com/htdocs/cache

fi

# Expire the cached template (in case we've made changes to it).
echo "delete template-new" | nc localhost 11211
