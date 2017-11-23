#!/bin/bash

# Change the timezone to Eastern
# sudo cp /usr/share/zoneinfo/US/Eastern /etc/localtime

# Remove all PHP packages (they may well be PHP 7)
#sudo apt-get -y purge `dpkg -l | grep php| awk '{print $2}' |tr "\n" " "`

# Use repo for PHP 5.6.
#sudo add-apt-repository -y ppa:ondrej/php

# Update the OS
#sudo apt-get update
#sudo apt-get -y upgrade

# Install necessary packages.
#sudo apt-get install -y php5.6-cli php5.6-mysql php5.6-curl php5.6-memcached composer zip ruby awscli

#cd ~
#wget https://aws-codedeploy-us-east-1.s3.amazonaws.com/latest/install
#chmod +x ./install
#sudo ./install auto
