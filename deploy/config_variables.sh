#!/bin/bash
#==================================================================================
# Uses environment variables to populate includes/settings.inc.php (and a few
# other config files) immediately prior to deployment. This allows secrets (e.g.,
# API keys) to be stored separately from the settings.
#
# This is run only in the "Deploy to AWS" job of .github/workflows/deploy.yml,
# which passes each secret explicitly to that one step. Never run it before
# anything is uploaded as a GitHub Actions artifact or otherwise published --
# the files it writes contain secrets in plain text.
#==================================================================================

set -euo pipefail

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
	OPENSTATES_KEY
	OPENVA_KEY
	VA_DECODED_KEY
	MAPBOX_TOKEN
	MEMCACHED_SERVER
	OPENAI_KEY
	SLACK_WEBHOOK
	API_URL
	AWS_SES_SMTP_USERNAME
	AWS_SES_SMTP_PASSWORD
	AWS_ACCESS_KEY
	AWS_SECRET_KEY
)

# If this is our staging site, use the staging database, and don't use Memcached
# shellcheck disable=SC2034 # read indirectly, via ${!i}, below
if [[ "${GITHUB_REF_NAME:-}" == "master" ]]
then
	PDO_DSN="${PDO_DSN_STAGING:-}"
	MYSQL_DATABASE="${MYSQL_DATABASE_STAGING:-}"
	MEMCACHED_SERVER=
fi

# Escape a value for use in the replacement half of a sed s||| command
sed_escape() {
	printf '%s' "$1" | sed -e 's/[\\|&]/\\&/g'
}

# Escape a value for use within a single-quoted PHP string
php_escape() {
	local value="${1//\\/\\\\}"
	printf '%s' "${value//\'/\\\'}"
}

# Build the sed scripts in memory, so that secrets never appear in a process's
# command-line arguments
php_script=""
conf_script=""
for i in "${variables[@]}"
do
	value="${!i:-}"

	if [[ -z "$value" ]]; then
		echo "No value set for $i"
	fi

	if [[ "$value" == *$'\n'* ]]; then
		echo "Error: the value for $i contains a newline" >&2
		exit 1
	fi

	php_script+="s|define('$i', '')|define('$i', '$(sed_escape "$(php_escape "$value")")')|g"$'\n'
	conf_script+="s|{$i}|$(sed_escape "$value")|g"$'\n'
done

# Duplicate the default setting file to populate our settings file
cp -f htdocs/includes/settings-default.inc.php htdocs/includes/settings.inc.php
printf '%s' "$php_script" | sed -i -f - htdocs/includes/settings.inc.php

# Perform the same for Sphinx, the MySQL export script, and Postfix/SES auth
for file in deploy/sphinx.conf deploy/database_export.sh deploy/postfix/sasl_passwd
do
	printf '%s' "$conf_script" | sed -i -f - "$file"
done
