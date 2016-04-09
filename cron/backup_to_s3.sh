#!/bin/bash

FILENAME=webroot-$(date +%Y-%m-%d).tar.gz

cd /vol/www/richmondsunlight.com/html/ || exit

# Backup just about everything.
echo 'Creating archive'
tar -czf /vol/www/richmondsunlight.com/backups/"$FILENAME" * --exclude=cache --exclude=finance --exclude='*.mp4' --exclude='*.mp3' --exclude='*.avi' --exclude=mirror --exclude='*.bill.jpg*' --exclude='*.name.jpg*' --exclude=downloads
if [ $? -eq 0 ]; then
		
	cd /vol/www/richmondsunlight.com/backups/ || exit
	
	# Copy the backup to S3
	aws s3 cp "$FILENAME" s3://backups.richmondsunlight.com --grants read=uri=http://acs.amazonaws.com/groups/global/AllUsers

	if [ $? -eq 0 ]; then

		# Delete the local backup
		rm -f ./"$FILENAME"
	fi
fi
