#!/bin/bash
#title        :video-trigger.sh
#description  :Checks whether it's time to capture legislative video.
#author       :Waldo Jaquith
#date         :2016-01-16
#version      :0.1
#usage        :video-trigger.sh
#notes        :Meant to be invoked by cron.
#==============================================================================

while getopts "c:t:" OPTION
do
	case $OPTION in
		c)
			CHAMBER=$OPTARG
			;;
		t)
			TIME=$OPTARG
			;;
	esac
done

# Set some default values.
if [ -z "$CHAMBER" ]; then
	CHAMBER="house"
fi
if [ -z "$TIME" ]; then
	TIME=300
fi

# Move into the working directory.
cd /vol/www/richmondsunlight.com/html/utilities || exit

TIME_UNTIL="$(./next_meeting.php -c "$CHAMBER")"
if [ "$TIME_UNTIL" -le "$TIME" ]; then
	./capture_video.sh -c "$CHAMBER"
fi
