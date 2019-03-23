#!/bin/bash

# If the site doesn't already exist, then this is a fresh server.
SITE_SET_UP="$(sudo apache2ctl -S |grep -c richmondsunlight.com)"
if [ "$SITE_SET_UP" -eq "0" ]; then

    # Set the timezone to Eastern
    sudo cp /usr/share/zoneinfo/US/Eastern /etc/localtime

    # Remove all PHP packages (they may well be PHP 7)
    dpkg -s php5.7
    if [ $? -eq 1 ]; then
        sudo apt-get -y purge $(dpkg -l | grep php| awk '{print $2}' |tr "\n" " ")

        # Add the PHP 5 repo
        sudo add-apt-repository -y ppa:ondrej/php
    fi

    # Add the Certbot repo
    dpkg -s certbot
    if [ $? -eq 1 ]; then
        sudo add-apt-repository -y ppa:certbot/certbot
    fi

    # Install all packages.
    sudo apt-get update
    sudo DEBIAN_FRONTEND=noninteractive apt-get -y upgrade
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 curl geoip-database git gzip unzip openssl php5.6 php5.6-mysql mysql-client php5.6-curl php5.6-mbstring php5.6-apc php5.6-mbstring php5.6-xml python python-pip s3cmd sphinxsearch wget awscli certbot python-certbot-apache

    # Install mod_pagespeed
    dpkg -s mod-pagespeed-beta
    if [ $? -eq 1 ]; then
        wget https://dl-ssl.google.com/dl/linux/direct/mod-pagespeed-beta_current_amd64.deb
        sudo dpkg -i mod-pagespeed-*.deb
        sudo apt-get -f install
        rm mod-pagespeed-*.deb
    fi

    # Install Certbot
    dpkg -s mod-pagespeed-beta
    if [ $? -eq 1 ]; then
        sudo apt-get install -y ruby
        wget https://aws-codedeploy-us-east-1.s3.amazonaws.com/latest/install
        chmod +x ./install
        sudo ./install auto
        rm install
    fi

    # Enable Sphinx's server
    echo "START=yes" | sudo tee /etc/default/sphinxsearch
    sudo /etc/init.d/sphinxsearch start
    
fi
