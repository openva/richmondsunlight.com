#!/bin/bash

if [ "$TRAVIS_BRANCH" = "master" ]
then
	echo "Configuring deployment to staging site"
	sed -i -e "s|/var/www/richmondsunlight.com|/var/www/staging.richmondsunlight.com|g" appspec.yml
fi
