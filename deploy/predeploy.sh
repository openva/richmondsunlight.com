#!/bin/bash

# If the site doesn't already exist, then this is a fresh server.
SITE_SET_UP="$(sudo apache2ctl -S |grep -c richmondsunlight.com)"
if [ "$SITE_SET_UP" -eq "0" ]; then

    # Set the timezone to Eastern
    sudo cp /usr/share/zoneinfo/US/Eastern /etc/localtime
    
    # Add swap space, if it doesn't exist
    if [ "$(grep -c swap /etc/fstab)" -eq "0" ]; then
        sudo /bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024
        sudo /sbin/mkswap /var/swap.1
        sudo chmod 600 /var/swap.1
        sudo /sbin/swapon /var/swap.1
        echo "/var/swap.1   swap    swap    defaults        0   0" | sudo tee /etc/fstab
    fi

    # Remove all PHP packages (they may well be PHP 7)
    dpkg -s php5.7
    if [ $? -eq 1 ]; then
        sudo apt-get -y purge $(dpkg -l | grep php| awk '{print $2}' |tr "\n" " ")

        # Add the PHP 5 repo
        sudo apt install python-software-properties
        sudo add-apt-repository -y ppa:ondrej/php
    
    fi

    # Add the Certbot repo
    dpkg -s certbot
    if [ $? -eq 1 ]; then
        sudo add-apt-repository -y ppa:certbot/certbot
    fi

    # Add the Yarn repo
    dpkg -s yarn
    if [ $? -eq 1 ]; then
        curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add -
        echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
    fi

    # Install all packages.
    sudo apt-get update
    sudo DEBIAN_FRONTEND=noninteractive apt-get -y upgrade
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 curl geoip-database git gzip \
    unzip openssl mysql-client \
    php5.6 php5.6-mysql php5.6-curl php5.6-mbstring php5.6-apc php5.6-xml php5.6-fpm \
    php7.4 php7.4-mysql php7.4-curl php5.6-mbstring php7.4-apc php7.4-xml php7.4-fpm\
    python python-pip s3cmd sphinxsearch wget awscli certbot \
    python-certbot-apache yarn redis-server php-redis \

    # Install mod_pagespeed
    dpkg -s mod-pagespeed-beta
    if [ $? -eq 1 ]; then
        wget https://dl-ssl.google.com/dl/linux/direct/mod-pagespeed-beta_current_amd64.deb
        sudo dpkg -i mod-pagespeed-*.deb
        sudo apt-get -f install
        rm mod-pagespeed-*.deb
    fi

    # Install Codedeploy
    if [ ! -d /opt/codedeploy-agent/ ]; then
        sudo apt-get install -y ruby
        wget https://aws-codedeploy-us-east-1.s3.amazonaws.com/latest/install
        chmod +x ./install
        sudo ./install auto
        rm install
    fi

    # Set up mail relay to AWS SES
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y postfix mailutils

    # First set append to the Postfix config file
    echo <<'EOF' | sudo tee -a /etc/postfix/main.cf
    smtp_sasl_security_options = noanonymous
    smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
    smtp_use_tls = yes
    smtp_tls_security_level = encrypt
    smtp_tls_note_starttls_offer = yes
    smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
EOF

    # Then set up the SES SMTP credentials in Postfix
    sudo mv sasl_passwd /etc/postfix/sasl_passwd
    sudo chown root:root /etc/postfix/sasl_passwd
    sudo chmod 0600 /etc/postfix/sasl_passwd

    # Create hashmap database
    sudo postmap hash:/etc/postfix/sasl_passwd
    sudo chown root:root /etc/postfix/sasl_passwd.db
    sudo chmod 0600 /etc/postfix/sasl_passwd.db

    # Tell Postfix where to find the SES certificate
    sudo postconf -e 'smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt'

    # Restart Postfix
    sudo service postfix restart

    # Enable Sphinx's server
    echo "START=yes" | sudo tee /etc/default/sphinxsearch
    sudo /etc/init.d/sphinxsearch start
    
fi
