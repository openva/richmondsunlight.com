FROM php:5-apache

# Replace sources.list with the archived repository URLs
RUN echo "deb http://archive.debian.org/debian/ stretch main non-free contrib" > /etc/apt/sources.list \
    && echo "deb-src http://archive.debian.org/debian/ stretch main non-free contrib" >> /etc/apt/sources.list \
    && echo "deb http://archive.debian.org/debian-security/ stretch/updates main" >> /etc/apt/sources.list \
    && echo "deb-src http://archive.debian.org/debian-security/ stretch/updates main" >> /etc/apt/sources.list

# Disable checking for valid signatures on the archived repositories
RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/90ignore-release-date

RUN docker-php-ext-install mysqli && a2enmod rewrite && a2enmod expires && a2enmod headers

# Install our packages
RUN apt --fix-broken install
RUN apt-get update
RUN apt-get install -y apt-transport-https ca-certificates gnupg2
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt-get update
# We use the most recent Yarn 1.X release (still quite old) to deal with our old environment.
RUN apt-get install -y git zip sphinxsearch zlib1g-dev jq yarn=1.22.19-1

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy over the deploy scripts
WORKDIR /var/www/
COPY . deploy/

EXPOSE 80

RUN /var/www/deploy/docker-setup-server.sh 

ENTRYPOINT ["apache2ctl", "-D", "FOREGROUND"]
