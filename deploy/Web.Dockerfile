FROM php:8.4-apache

# Disable checking for valid signatures on the archived repositories
RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/90ignore-release-date \
    && echo 'Acquire::AllowInsecureRepositories "true";' >> /etc/apt/apt.conf.d/90ignore-release-date \
    && echo 'Acquire::AllowDowngradeToInsecureRepositories "true";' >> /etc/apt/apt.conf.d/90ignore-release-date

# Install GD dependencies and ImageMagick/Ghostscript for preview image generation
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    ghostscript \
    imagemagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd \
    && a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN apt update && \
    apt upgrade -y && \
    apt install -y gnupg2 curl libmemcached-dev zlib1g-dev libssl-dev

# Install Node.js from NodeSource
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y nodejs

# Install Yarn via npm
RUN npm install -g yarn

# Install remaining packages
RUN apt-get install -y git zip sphinxsearch zlib1g-dev jq

# Install PHP memcached extension
RUN pecl install memcached && docker-php-ext-enable memcached

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy over the webroot
COPY deploy/ /var/www/deploy/

EXPOSE 80

RUN /var/www/deploy/docker-setup-server.sh 

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
