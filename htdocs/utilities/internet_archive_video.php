<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

/*
 * Create a list of every video that isn't stored on Archive.org.
 */
$sql = 'SELECT id, chamber, path AS file, date, DATE_FORMAT(date, "%M %d, %Y") AS date_formatted,
		sponsor
		FROM files
		WHERE type="video" AND path NOT LIKE "http://archive.org%"';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	die('No non-archive.org files were found.');
}
$videos = array();
$ids = array();
while ($video = mysql_fetch_assoc($result))
{
	
	array_walk($video, 'stripslashes');
	$video['mediatype'] = 'movies';
	$video['collection'] = 'virginiageneralassembly';
	$video['item'] = $video['chamber'].str_replace('-', '', $video['date']);
	if ($video['chamber'] == 'house')
	{
		$video['chamber'] = 'House of Delegates';
	}
	else
	{
		$video['chamber'] = 'Senate';
	}
	$video['title'] = $video['date_formatted'] . ' Virginia ' . $video['chamber'] . ' Floor Session';
	$video['creator'] = 'Virginia '.ucwords($video['chamber']);
	$video['subject'] = 'legislature;virginia;government;politics';
	$video['language'] = 'eng';
	$video['sponsor'] = strip_tags($video['sponsor']);
	$video['file'] = $_SERVER['DOCUMENT_ROOT'] . substr($video['file'], 1);
	
	if (file_exists($video['file']) === FALSE)
	{
		continue;
	}
	
	/*
	 * Set aside a copy of the ID to use to update this record.
	 */
	$tmp = pathinfo($video['file']);
	$ids[$video{id}] = 'http://archive.org/download/'.$video['item'].'/'.$tmp['filename'].'.mp4';
	
	unset($video['chamber']);
	unset($video['date_formatted']);
	$videos[] = $video;
	
}

/*
 * Check Archive.org to see if the file already exists. If it does, do not add it to the CSV, but
 * instead, delete it, and update the database.
 */
foreach ($ids as $id => $url)
{

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
	$response = curl_exec($ch);
	
	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '404')
	{
		echo '<li>ID ' . $id.' not found at ' . $url . '. Adding to CSV.</li>';
		continue;
	}
	
	/*
	 * This file exists on Archive.org already. Update the database and delete the file.
	 */
	else
	{

		$sql = 'UPDATE files
				SET path="' . $url . '"
				WHERE id=' . $id;
		$result = mysql_query($sql);
		
		if ($result === FALSE)
		{
			die('Halting because the database table couldn’t be updated: <code>' . $sql . '</code>');
		}
		
		echo '<p>Updated ' . $video['file'] . ' to ' . $url . ' in the database.</p>';
		
		foreach ($videos as $key => $video)
		{
	
			if ($video['id'] == $id)
			{
				if (unlink($video['file']) == TRUE)
				{
					echo '<p>Deleted ' . $video['file'] . ', because it’s on Archive.org.</p>';
				}
				else
				{
					echo '<p>Couldn’t delete ' . $video['file'] . '—please delete it manually.</p>';
				}
				unset($videos[$key]);
				break;
			}
		
		}
		
	}
}

if (count($videos) == 0)
{
	die('<p>No videos need to be uploaded.</p>');
}

foreach ($videos as &$video)
{
	unset($video['id']);
}

if (is_writable($_SERVER['DOCUMENT_ROOT'] . 'video/metadata.csv') == FALSE)
{
	die('Cannot write to ' . $_SERVER['DOCUMENT_ROOT'] . 'video/metadata.csv');
}

$output_file = $_SERVER['DOCUMENT_ROOT'] . 'video/metadata.csv';
$out = fopen($output_file, 'w');
$header = array();
foreach ($videos[0] as $key => $value)
{
	$header[] = $key;
}
fputcsv($out, $header);

# We don't use a foreach() loop because, when there are only two videos, it fails to advance, but
# instead just returns the first video twice. I have no idea why.
for ($i=0; $i<count($videos); $i++)
{
	unset($videos[$i]['id']);
	fputcsv($out, $videos[$i]);
}
fclose($out);

echo '
	<p>Video metadata exported to <code>' . $_SERVER['DOCUMENT_ROOT'] . 'video/metadata.csv</code>.</p>
	
	<p>In <code>' . $_SERVER['DOCUMENT_ROOT'] . 'video/</code>, run <code>./ias3upload.pl -k
		accesskey:secretkey</code>. (Get the keys at
		<a href="http://archive.org/account/s3.php">http://archive.org/account/s3.php</a>.)</p>
		
	<p>After running it, refresh this page, to update the database to point to the new paths and
	to delete the files from this server.</p>';
