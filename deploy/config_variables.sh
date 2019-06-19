#!/bin/bash
#==================================================================================
# Uses environment variables within Travis CI to populate includes/settings.inc.php
# prior to deployment. This allows secrets (e.g., API keys) to be stored in Travis,
# while the settings file is stored on GitHub.
#==================================================================================

# Define the list of environment variables that we may want to populate during
# deployment.
variables=(
	LIS_FTP_USERNAME
	LIS_FTP_PASSWORD
	PDO_DSN
	PDO_SERVER
	PDO_USERNAME
	PDO_PASSWORD
	MYSQL_DATABASE
	GMAPS_KEY
	GEOPARSER_KEY
	OPENSTATES_KEY
	OPENVA_KEY
	VA_DECODED_KEY
	MAPBOX_TOKEN
	MEMCACHED_SERVER
	PUSHOVER_KEY
	SLACK_WEBHOOK
	API_URL
)

# Iterate over the variables and warn if any aren't populated
for i in "${variables[@]}"
do
	if [ -z "${!i}" ]; then
		echo "No value set for $i"
	fi
done

# Duplicate the default setting file to populate our settings file
cp htdocs/includes/settings-default.inc.php htdocs/includes/settings.inc.php

# If this is our staging site
if [ "$TRAVIS" = true ]&& [ "$TRAVIS_BRANCH" = "master" ]
then

	# Set the PDO_DSN value to that of our staging database
	sed -i -e "s|define('PDO_DSN', '')|define('PDO_DSN', '${PDO_DSN_STAGING}')|g" htdocs/includes/settings.inc.php
	sed -i -e "s|define('MYSQL_DATABASE', '')|define('MYSQL_DATABASE', '${MYSQL_DATABASE_STAGING}')|g" htdocs/includes/settings.inc.php
	
	# Don't use Memcached at all
	MEMCACHED_SERVER=
fi

# Now iterate over again and perform the replacement
for i in "${variables[@]}"
do
	sed -i -e "s|define('$i', '')|define('$i', '${!i}')|g" htdocs/includes/settings.inc.php
done

# Perform the same for Sphinx
for i in "${variables[@]}"
do
	sed -i -e "s|{$i}|${!i}|g" deploy/sphinx.conf
done

# Perform the same for the MySQL export script
for i in "${variables[@]}"
do
	sed -i -e "s|{$i}|${!i}|g" deploy/database_export.sh
done
