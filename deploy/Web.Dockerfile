FROM php:5.6.39-apache
RUN docker-php-ext-install mysqli && docker-php-ext-install mysql && a2enmod rewrite && a2enmod expires

RUN apt --fix-broken install
RUN apt-get update
RUN apt-get install -y git zip libmemcached-dev zlib1g-dev \
    && pecl install memcached-2.2.0 \
	&& docker-php-ext-enable memcached

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN echo "extension=memcached.so" >> /usr/local/etc/php/php.ini

WORKDIR /var/www/

COPY . deploy/
RUN deploy/docker-setup.sh

WORKDIR /var/www/html/

EXPOSE 80
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
