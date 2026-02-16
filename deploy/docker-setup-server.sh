#!/bin/bash

# Make localhost the name of the host
grep "#ServerName www.example.com" /etc/apache2/sites-enabled/000-default.conf
if [ $? -eq 0 ]; then
    sed -i 's/#ServerName www.example.com/ServerName localhost/g' /etc/apache2/sites-enabled/000-default.conf
fi

# Make /var/www/htdocs the webroot
grep "DocumentRoot /var/www/html" /etc/apache2/sites-enabled/000-default.conf
if [ $? -eq 0 ]; then
    sed -i 's/html/htdocs/g' /etc/apache2/sites-enabled/000-default.conf
fi

# Add RewriteMap tolower so that ${tolower:...} works in .htaccess
if ! grep -q "RewriteMap tolower" /etc/apache2/sites-enabled/000-default.conf; then
    sed -i 's|DocumentRoot /var/www/htdocs|DocumentRoot /var/www/htdocs\n\tRewriteMap tolower int:tolower|' /etc/apache2/sites-enabled/000-default.conf
fi

# If the php.ini doesn't exist, create it.
if [ ! -f "/usr/local/etc/php/php.ini" ]; then
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
fi

# Configure Apache security settings
if ! grep -q "ServerTokens Prod" /etc/apache2/conf-available/security.conf; then
    sed -i 's/ServerTokens OS/ServerTokens Prod/g' /etc/apache2/conf-available/security.conf
fi

if ! grep -q "ServerSignature Off" /etc/apache2/conf-available/security.conf; then
    sed -i 's/ServerSignature On/ServerSignature Off/g' /etc/apache2/conf-available/security.conf
fi

# Allow ImageMagick to process PDFs (required for bill preview image generation)
if [ -f "/etc/ImageMagick-6/policy.xml" ]; then
    sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/g' /etc/ImageMagick-6/policy.xml
fi

