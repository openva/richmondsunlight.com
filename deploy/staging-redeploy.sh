#!/bin/bash

# Clone a new copy of the source
cd /vol/www/ || exit
mkdir -p staging.richmondsunlight.com/ && cd staging.richmondsunlight.com && aws s3 cp s3://deploy.richmondsunlight.com/staging.zip staging.zip && unzip -o staging.zip && rm staging.zip

# Duplicate the database.
#mysqldump -u {PDO_USERNAME} --password={PDO_PASSWORD} -h {PDO_SERVER} {MYSQL_DATABASE} | mysql -u {PDO_USERNAME} --password={PDO_PASSWORD} -h {PDO_SERVER} rs-staging
