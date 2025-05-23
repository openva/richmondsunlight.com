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
WORKDIR /var/www/
COPY . deploy/

EXPOSE 80

RUN /var/www/deploy/docker-setup-server.sh 

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
