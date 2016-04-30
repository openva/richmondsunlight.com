#!/usr/bin/php

<?php

/**
 * Time-shift SRT files
 *
 * Takes input in the form of an SRT file, returns an identical SRT file, with all timestamps
 * adjusted the specified amount. Results are sent to STDOUT.
 *
 * This would shift transcript.srt by 60 seconds: 
 *
 * shift_captions.php -f transcript.srt -i 60
 *
 * Negative numbers are accepted.
 */

ini_set('display_errors', 1);
ini_set('error_reporting', 'E_ALL');
error_reporting(1);

require('/vol/www/richmondsunlight.com/html/includes/settings.inc.php');
require('/vol/www/richmondsunlight.com/html/includes/functions.inc.php');
require('/vol/www/richmondsunlight.com/html/includes/class.Video.php');

/*
 * Require a "-t" flag and a "-f" flag, indicating time and input filename, respectively.
 */
$options = getopt('f:t:');

/*
 * Make sure time exists.
 */
if (!isset($options['t']))
{
	echo "Error: Time offset, in seconds, must be specified with -t (e.g., “-t 12)\n";
	exit(1);
}

/*
 * Make sure filename exists.
 */
if (!isset($options['f']))
{
	echo "Error: Input filename must be provided with -f (e.g., “-f 20180201.srt)\n";
	exit(1);
}

/*
 * Localize the variables.
 */
$time = $options['t'];
$filename = $options['f'];

/*
 * Make sure that the time is valid.
 */
if (is_numeric($time) == FALSE)
{
	echo "Error: Time offset must be specified in seconds (e.g., “-t 12)\n";
	exit(1);
}
elseif ( ($time == 0) || abs($time) > 18000 )
{
	echo "Error: Time offset must be between 1 and 18000 seconds\n";
	exit(1);
}

/*
 * Make sure that the filename is valid and accessible.
 */
if (file_exists($filename) == FALSE)
{
	echo "Error: $filename does not exist\n";
	exit(1);
}
elseif (is_readable($filename) == FALSE)
{
	echo "Error: $filename cannot be read\n";
	exit(1);	
}

$captions = new Video;
$captions->srt = file_get_contents($filename);
$captions->normalize_line_endings();
$captions->offset = $time;
$captions->time_shift_srt();
echo $captions->srt;
