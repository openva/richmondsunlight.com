#!/bin/bash

if [ "$TRAVIS_BRANCH" = "master" ]
then
	echo "Configuring deployment to staging site"
	sed -i -e "s|/vol/www/richmondsunlight.com|/vol/www/test.richmondsunlight.com|g" appspec.yml
fi
