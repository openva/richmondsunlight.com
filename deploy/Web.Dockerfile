FROM php:5.6.39-apache
RUN docker-php-ext-install mysqli && docker-php-ext-install mysql && a2enmod rewrite && a2enmod expires

# Install our packages
RUN apt --fix-broken install
RUN apt-get update
RUN apt-get install -y git zip libmemcached-dev zlib1g-dev \
    && pecl install memcached-2.2.0 \
	&& docker-php-ext-enable memcached

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy over the deploy scripts
WORKDIR /var/www/
COPY . deploy/

EXPOSE 80

RUN deploy/docker-setup-server.sh 

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
