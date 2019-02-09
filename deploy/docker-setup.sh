#!/bin/bash

# What this image calls html, we call htdocs
ln -s html htdocs

# Install Composer dependencies
cd htdocs
composer install
cd ..

# Move over the settings file.
cp deploy/settings-docker.inc.php htdocs/includes/settings.inc.php
