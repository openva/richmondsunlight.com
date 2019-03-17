#!/bin/bash

# Get the API repo.
if [ ! -d "api/" ]; then

    # Download the ZIP file.
    echo "Downloading API repository..."
    curl -s -L -o api.zip https://github.com/openva/rs-api/archive/master.zip
    if [ $? -ne 0 ]; then
        echo "Error: could not download API repository code. Quitting."
        exit 1;
    fi;
    
    unzip api.zip

    mv rs-api-master/ api/

    # Copy over the includes
    cd api/htdocs/
    cp -R ../../htdocs/includes/ includes
    cd ../..
    
    # Remove artifacts.
    rm api.zip
fi

# Stand it up.
docker-compose build && docker-compose up -d

WEB_ID=$(docker ps |grep rs_web |cut -d " " -f 1)
docker exec "$WEB_ID" /var/www/deploy/docker-setup-site.sh
