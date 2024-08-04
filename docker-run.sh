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
    curl -s -L -o api.zip https://github.com/openva/rs-api/archive/master.zip
    if [ $? -ne 0 ]; then
        echo "Error: could not download API repository code. Quitting."
        exit 1;
    fi;
    
    unzip api.zip

    mv rs-api-master/ api/

    # Concatenate the database dumps into a single file, for MariaDB to load
    cd deploy/
    cat mysql/structure.sql mysql/basic-contents.sql mysql/test-records.sql > ../api/deploy/database.sql
    cd ..
    
    # Remove artifacts
    rm api.zip
fi

# Stand it up
docker compose build && docker compose up -d

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
SITE_URL="http://localhost:5000/"
if curl --output /dev/null --silent --head --fail "$SITE_URL"; then
    echo "Site is up and running at $SITE_URL"
else
    echo "Site is not running or not reachable at $SITE_URL"
fi
