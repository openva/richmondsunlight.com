FROM php:8-apache

# Disable checking for valid signatures on the archived repositories
RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/90ignore-release-date \
    && echo 'Acquire::AllowInsecureRepositories "true";' >> /etc/apt/apt.conf.d/90ignore-release-date \
    && echo 'Acquire::AllowDowngradeToInsecureRepositories "true";' >> /etc/apt/apt.conf.d/90ignore-release-date

RUN docker-php-ext-install mysqli && a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN apt update && \
    apt upgrade -y && \
    apt install -y gnupg2 curl redis libmemcached-dev zlib1g-dev libssl-dev

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/yarn.gpg
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update
RUN apt-get install -y git zip sphinxsearch zlib1g-dev jq yarn

# Install PHP memcached extension
RUN pecl install memcached && docker-php-ext-enable memcached

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy over the webroot
COPY deploy/ /var/www/deploy/

EXPOSE 80

RUN /var/www/deploy/docker-setup-server.sh 

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
