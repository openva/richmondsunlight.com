FROM php:8-apache

# Disable checking for valid signatures on the archived repositories
RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/90ignore-release-date

RUN docker-php-ext-install mysqli && a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y gnupg2 curl

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update
RUN apt-get install -y git zip sphinxsearch zlib1g-dev jq yarn

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy over the deploy scripts
#### HERE'S THE PROBLEM YOU'RE WORKING ON
## docker-compose.yml establishes that deploy/ is the build context, and yet
## this copy command is copying over the entire webroot. The file that we need
## to run is in /var/www/deploy/deploy/, instead of /var/www/deploy/. Why?
COPY ./deploy/ /var/www/deploy/
RUN ls -l .
RUN ls -l /var/www/
RUN ls -l /var/www/deploy/
RUN ls -l /var/www/deploy/deploy/

EXPOSE 80

RUN /var/www/deploy/docker-setup-server.sh 

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
