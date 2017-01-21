<?php

ini_set('display_errors', 1);
ini_set('error_reporting', 'E_ALL');
error_reporting(1);

require_once('../includes/settings.inc.php');
require_once('../includes/functions.inc.php');

# Connect to the database.
$mdb2 = connect_to_db('pdo');

# Convert a count of seconds to HH:MM:SS format.
function format_time($secs)
{
	return gmdate('H:i:s', $secs);
}

// Invoke mplayer, convert, and the OCR software from here, using proc_open to fire each of them
// up, proc_get_status to keep tabs on it, and proc_close to wrap each one up.

// Get the "where" from...well, something.
// Set capture_rate by dividing the total number of frames in the video by the total number of
// frames in the video directory. That'll almost certainly require some rounding.

if (!isset($_GET['id']) || empty($_GET['id']))
{
	die('No video ID specified.');
}

$sql = 'SELECT *
		FROM video_index
		WHERE file_id=' . $_GET['id'];
$result = $mdb2->query($sql);
if ($result->rowCount() > 0)
{
	die('This video has already been parsed!');
}

$sql = 'SELECT chamber, path, capture_directory, length, capture_rate, capture_directory, date,
		fps, width, height
		FROM files
		WHERE id='.$_GET['id'];
$result = $mdb2->query($sql);
if ($result->rowCount() == 0)
{
	die('Invalid video ID specified '.$sql);
}
$file = $result->fetch();
$file = array_map('stripslashes', $file);

# If we're missing some basic information, start by trying to fill it in from available data within
# the database.
if (empty($file['capture_directory']) && !empty($file['date']))
{
	$file['capture_directory'] = '/video/'.$file['chamber'].'/floor/'
		.str_replace('-', '', $file['date']).'/';
		
	# If the directory turns out not to exist, though, abandon ship.
	if (!file_exists($_SERVER['DOCUMENT_ROOT'].$file['capture_directory']))
	{
		echo '<p>No such directory as '.$file['capture_directory'].'</p>';
		echo '<p>You must go to the command line and run ~/process-video '.$file['capture_directory'].' [chamber]';
		unset($file['capture_directory']);
	}
}
if (empty($file['path']) && !empty($file['date']))
{
	$file['path'] = '/floor/'.str_replace('-', '', $file['date']).'.mp4';
}
		
$vid = new Video;
$vid->path = $file['path'];
$vid->capture_directory = $file['capture_directory'];
$vid->extract_file_data();

# If we have a file length of zero, just unset the variable so that we can repopulate it.
if ($file['length'] == '00:00:00')
{
	unset($file['length']);
}

foreach ($vid as $key => $value)
{
	if (!isset($file[$key]) || empty($file[$key]))
	{
		$file[$key] = $value;
	}
}

# Now store these new bits of information about the video in the database.
$sql = 'UPDATE files
		SET fps='.$file['fps'].', width='.$file['width'].', height='.$file['height'].',
		length="'.$file['length'].'", path="'.$file['path'].'",
		capture_rate='.$file['capture_rate'].',
		capture_directory="'.$file['capture_directory'].'"
		WHERE id='.$_GET['id'];
$mdb2->query($sql);

# Store the environment variables.
$video['id'] = $_GET['id'];
$video['chamber'] = $file['chamber'];	// The chamber of this video.
$video['where'] = 'floor';				// Where the video was taken. Most will be "floor."
$video['date'] = $file['date'];			// The date of the video in question.
$video['fps'] = $file['fps'];			// The frames per second at which the video was played.
$video['capture_rate'] = $file['capture_rate'];	// We captured every X frames. "Framestep," in mplayer terms.
$video['dir'] = $_SERVER['DOCUMENT_ROOT'] . $file['capture_directory'];

# Iterate through the video array and make sure nothing is blank. If so, bail.
foreach ($video as $name => $option)
{
	if (empty($option))
	{
		die('Cannot parse video without specifying '.$name.' in the files table.');
	}
}

# Store the directory contents as an array.
$dir = scandir($video['dir']);

# Iterate through every file in the directory.
foreach ($dir as $file)
{
	
	# Save the image number for use later. Note that this is not the literal frame number from the
	# video, but rather just the capture number. That is, there might be 300 frames of video in
	# 10 seconds of video, but if we capture just 2 screenshots in those 10 seconds, the first frame
	# number will be 1 and the second will be 2.
	$image_number = substr($file, 0, 8);
	
	if (substr($file, -4) != '.txt')
	{
		continue;
	}

	# If the filename indicates that this is a bill number
	if (strstr($file, 'bill'))
	{
		$bill = trim(file_get_contents($video['dir'].$file));
		$type = 'bill';
	}

	# Otherwise if the filename indicates that this is a legislator's name
	elseif (strstr($file, 'name'))
	{
	
		$legislator = trim(file_get_contents($video['dir'].$file));
	
		# Fix a common OCR mistake.
		$legislator = str_replace('—', '-', $legislator);
		
		$type = 'legislator';
	
	}
	
	# Check to see if these are really blank or implausibly short and, if so, don't actually
	# store them.
	if ( ($type == 'bill') && ( empty($bill) || (strlen($bill) < 3) ) )
	{
		continue;
	}
	elseif ( ($type == 'legislator') && (empty($legislator) || (strlen($legislator) < 10) ) )
	{
		continue;
	}

	# If this string consists of a low percentage of low-ASCII characters, we can skip it.
	if ( (isset($bill) && strlen($bill) > 0 ) )
	{
	
		$invalid = 0;
		foreach (str_split($bill) as $character)
		{
			if (ord($str) > 127)
			{
				$invalid++;
			}
		}
		if ( ($invalid / strlen($bill)) > .33)
		{
			unset($bill);
		}
	
	}
	elseif (isset($legislator))
	{
	
		$invalid = 0;
		foreach (str_split($legislator) as $character)
		{
			if (ord($str) > 127)
			{
				$invalid++;
			}
		}
		if ( ($invalid / strlen($bill)) > .33)
		{
			unset($legislator);
		}
	
	}

	# If the bill has no numbers, then it's not a bill.
	if (!eregi('[0-9]', $bill))
	{
		unset($bill);
	}

	# If the legislator chyron lacks three consecutive letters, it's probably not a
	# legislator (or, if it is, we'll never figure it out).
	if (!eregi('([a-z]{3})', $legislator))
	{
		unset($legislator);
	}

	# If we've successfully gotten a bill number.
	if (isset($bill) || isset($legislator))
	{
		
		# Determine how many seconds into this video this image appears, converting it (with
		# a custom function) into HH:MM:SS format, stepping back five seconds as a buffer.
		$time = format_time( (($video['capture_rate'] / $video['fps']) * $image_number) -5 );
		
		# Assemble the beginnings of a SQL string.
		$sql = 'INSERT INTO video_index
				SET file_id=' . $video['id'] . ', time="' . $time . '",
				screenshot="' . $image_number . '", date_created=now(), ';
	
		if (isset($bill))
		{
	
			# Finish assembling the SQL string.
			$sql .= 'type="bill", raw_text="' . addslashes($bill) . '"';
		
			echo '<li>' . $bill . '</li>';
		
			# Unset this variable so that we won't use it the next time around.
			unset($bill);
		
		}
	
		# Else if we've successfully gotten a legislator's name.
		elseif (isset($legislator))
		{
	
			# Finish assembling the SQL string.
			$sql .= 'type="legislator", raw_text="' . addslashes($legislator) . '"';
		
			echo '<li>' . $legislator . '</li>';
		
			# Unset this variable so that we won't use it the next time around.
			unset($legislator);
		
		}
	
		$result = $mdb2->query($sql);
		/*if (PEAR::isError($result))
		{
			echo '<p style="color: #f00;">Failed: ' . $sql . '</p>';
		}*/
	
		unset($sql);
	}
	
	# Delete this file, now that we've handled it.
	unlink($video['dir'] . '/' . $file);
	
	# We've used this a few times here, so let's unset it, just in case.
	unset($tmp);
}

/*
// This isn't going to work -- the web user doesn't have permissions.
exec('/home/ubuntu/youtube-upload-master/bin/youtube-upload '
	. '--tags="virginia, legislature, general assembly" '
	. '--default-language="en" '
	. '--default-audio-language="en" '
	. '--title="Virginia ' . ucfirst($video['chamber']) . ', ' . date('F j, Y', strtotime($video['date'])) . '" '
	. '--recording-date="' . $video['date'] . 'T00:00:00.0Z" '
	. $video['dir'] . ' &> /dev/null &');
echo '<p>Uploading video <a href="https://www.youtube.com/channel/UCt0nWtbTmFhYuwnDfAHh8Pw">to YouTube</a>.</p>';*/

echo '<p><a href="/utilities/resolve_chyrons.php?id=' . trim($_GET['id']) . '">Resolve Chyrons &gt;&gt;</a></p>';

