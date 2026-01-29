#!/bin/bash
set -e

# Save the current directory, to return to at the end
CWD=$(pwd)

# Change to the directory that this script is in
cd $(dirname "$0") || exit

# Get the API repo.
if [ ! -d "api/" ]; then

    # Download the ZIP file
    echo "Downloading API repository..."
    curl -s -L -o api.zip "https://github.com/openva/rs-api/archive/master.zip"
    if [ $? -ne 0 ]; then
        echo "Error: could not download API repository code. Quitting."
        exit 1;
    fi;
    
    unzip api.zip

    mv rs-api-master/ api/

    # Remove artifacts
    rm api.zip
fi

# Concatenate the database dumps into a single file, for MariaDB to load
cat deploy/mysql/structure.sql deploy/mysql/basic-contents.sql deploy/mysql/test-records.sql \
    deploy/mysql/test-users.sql deploy/mysql/video-index.sql > api/deploy/database.sql

# Stand it up
if docker image inspect rs_web:ci >/dev/null 2>&1; then
    echo "Found prebuilt rs_web:ci image; using it without rebuild."
    docker compose up -d
else
    docker compose build && docker compose up -d
fi

# Wait for MariaDB to be available
while ! nc -z localhost 3306; do sleep 1; done

# Run the site setup script
WEB_ID=$(docker ps |grep rs_web |cut -d " " -f 1)
docker exec "$WEB_ID" /var/www/deploy/docker-setup-site.sh

# Copy over the API includes
cd api/htdocs/ || exit
cp -R ../../htdocs/includes/ includes/
cd ../../

# Return to the original directory
cd "$CWD" || exit

# Check if the site is running
SITE_URL="http://localhost:8000/"
if curl --output /dev/null --silent --head --fail "$SITE_URL"; then
    echo "Site is up and running at $SITE_URL"
else
    echo "Site is not running or not reachable at $SITE_URL"
fi
