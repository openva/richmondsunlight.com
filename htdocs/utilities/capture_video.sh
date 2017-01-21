#!/bin/bash
#title        :capture-video.sh
#description  :Captures video from Virginia House and Senate chambers.
#author       :Waldo Jaquith
#date         :2016-01-15
#version      :0.1
#usage        :capture-video.sh -c [chamber] -o [filename]
#notes        :
#==============================================================================

while getopts "c:o:" OPTION
do
	case $OPTION in
		c)
			CHAMBER=$OPTARG
			;;
		o)
			FILENAME=$OPTARG
			;;
	esac
done

# Set a default filename.
if [ -z "$FILENAME" ]; then
	FILENAME="$CHAMBER"-"$(date +%Y%m%d)".m4v
fi

if [ "$CHAMBER" = "house" ]; then
	URL="rtmp://granicus.mpl.miisolutions.net/granicus-livepull-us-1/virginia-house/mp4:G0890_002"
elif [ "$CHAMBER" = "senate" ]; then
	URL="rtmp://granicus.mpl.miisolutions.net/granicus-livepull-us-2/virginia-senate/mp4:G0889_002"
fi

#mencoder -nocache -of lavf -lavfopts format=mp4 -vf harddup -ofps 29.97 -rtsp-stream-over-tcp "$URL" -oac mp3lame -ovc copy  -o ../video/incoming/"$FILENAME"
vlc "$URL" --sout=file/avi:../video/incoming/"$FILENAME"
