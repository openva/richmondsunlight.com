#!/bin/bash

# If the site doesn't already exist, then this is a fresh server.
SITE_SET_UP="$(sudo apache2ctl -S |grep richmondsunlight.com |wc -l)"
if [ "SITE_SET_UP" -eq "0" ]; then

    # Set the timezone to Eastern
    sudo cp /usr/share/zoneinfo/US/Eastern /etc/localtime

    # Remove all PHP packages (they may well be PHP 7)
    sudo apt-get -y purge `dpkg -l | grep php| awk '{print $2}' |tr "\n" " "`

    # Add the PHP 5 repo
    sudo add-apt-repository -y ppa:ondrej/php

    # Add the Certbot repo
    sudo add-apt-repository -y ppa:certbot/certbot

    # Install all packages.
    sudo apt-get update
    sudo DEBIAN_FRONTEND=noninteractive apt-get -y upgrade
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 curl geoip-database git gzip unzip openssl php5.6 php5.6-mysql mysql-client php5.6-curl php5.6-mbstring php5.6-apc php5.6-mbstring php5.6-xml python python-pip s3cmd sphinxsearch wget awscli certbot python-certbot-apache

    # Install mod_pagespeed
    wget https://dl-ssl.google.com/dl/linux/direct/mod-pagespeed-beta_current_amd64.deb
    sudo dpkg -i mod-pagespeed-*.deb
    sudo apt-get -f install
    rm mod-pagespeed-*.deb

    # Install Certbot
    sudo apt-get install -y ruby
    wget https://aws-codedeploy-us-east-1.s3.amazonaws.com/latest/install
    chmod +x ./install
    sudo ./install auto
    rm install
    
fi
