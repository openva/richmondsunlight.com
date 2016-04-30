<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# Open a MySQL connection.
require_once 'MDB2.php';
$dsn = PDO_DSN;
$mdb2 = MDB2::factory($dsn);
$mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);

define(FACE_API_KEY, '');
define(FACE_API_SECRET, '');

if (!isset($_GET['op']))
{
	echo '
	<ul>
		<li><a href="/utilities/detect_faces.php?op=train">Train</a></li>
		<li><a href="/utilities/detect_faces.php?op=detect">Detect</a></li>
		<li><a href="/utilities/detect_faces.php?op=store">Store</a></li>
	</ul>';
}

# Training mode
if ($_GET['op'] == 'train')
{
	$sql = 'SELECT video_index.id, representatives.shortname,
				(SELECT COUNT( * )
				FROM video_index
				WHERE video_index.type = "legislator"
				AND video_index.linked_id=representatives.id
				AND video_index.face_json IS NOT NULL) AS number,
			CONCAT(files.capture_directory, video_index.screenshot, ".jpg") AS image
			FROM video_index
			LEFT JOIN files
				ON video_index.file_id = files.id
			LEFT JOIN representatives
				ON video_index.linked_id=representatives.id
			WHERE video_index.face_json IS NULL
			AND video_index.type="legislator"
			AND files.capture_directory IS NOT NULL 
			AND video_index.linked_id IS NOT NULL
			AND ((video_index.screenshot % 2)=0)
			HAVING number < 100
			ORDER BY number ASC
			LIMIT 15';
	
	$result = $mdb2->query($sql);
	
	if (PEAR::isError($result))
	{
		die($result->getMessage());
	}
	
	echo '<html>
		<head>
			<meta http-equiv=Refresh CONTENT="60; URL=http://www.richmondsunlight.com/utilities/detect_faces.php?op=train">
			<style>
				div {
					width: 300px;
					float: left;
					margin: 10px;
				}
					div.failed {
						background-color: red;
					}
					div.succeeded {
						background-color: green;
					}
					div > img {
						width: 280px;
					}
			</style>
		</head>
		<body>';
	
	while ($screenshot = $result->fetchRow())
	{
	
		$json = get_content('http://api.face.com/faces/detect.json?api_key='.FACE_API_KEY.'&api_secret='
			.FACE_API_SECRET.'&urls=http://www.richmondsunlight.com/'.$screenshot['image']);
		
		/// DETECT ERROR CODES <http://developers.face.com/docs/api/return-values/>
		if ( ($json !== false) && !empty($json) )
		{
			$sql = 'UPDATE video_index
					SET face_json = "'.addslashes($json).'"
					WHERE id='.$screenshot['id'];
			$mdb2->exec($sql);
		}
	
		$facedata = json_decode($json);
		$facedata = $facedata['photos'][0]['tags'];
		
		# If only one face is in this photo
		if (count($facedata) == 1)
		{
			$face = current($facedata);
		}
		
		# If more than one face is in this photo, we have to select the one that's most likely to be
		# the face of the subject.
		elseif (count($facedata) > 1)
		{
			# Go with whichever one is within 10% of the horizontal center of the image
			foreach ($facedata as $individual)
			{
				if ( ($individual['center']['x'] > 40) && ($individual['center']['x'] < 60) )
				{
					$face = $individual;
					break;
				}
			}
		}
		
		if ($face['recognizable'] != 1)
		{
		
		echo '<div class="failed">
				<img src="'.$screenshot['image'].'" />
				<p>'.$screenshot['shortname'].'</p>
				</div>';
			continue;
		}
		
		$json = get_content('http://api.face.com/tags/save.json?api_key='.FACE_API_KEY.'&api_secret='
			.FACE_API_SECRET.'&uid='.$screenshot['shortname'].'@richmondsunlight.com&tids='.$face['tid']);
		
		echo '<div class="succeeded">
				<img src="'.$screenshot['image'].'" />
				<p>'.$screenshot['shortname'].'</p>
			</div>';
		
		get_content('http://api.face.com/faces/train.json?api_key='.FACE_API_KEY.'&api_secret='
			.FACE_API_SECRET.'&uids='.$screenshot['shortname'].'@richmondsunlight.com');
		
		unset($face);
	}
	echo '</body></html>';
}

# Detection mode
elseif ($_GET['op'] == 'detect')
{

	# Get a listing of all senators, by shortname and ID.
	$sql = 'SELECT id, shortname
			FROM representatives
			WHERE chamber="senate"';
	$result = $mdb2->query($sql);
	$senators = array();
	while ($tmp = $result->fetchRow())
	{
		$senators[$tmp{'id'}] = $tmp['shortname'];
	}
	$senators_list = implode('@richmondsunlight.com,', $senators);
	
	# And a listing of all delegates, by shortname and ID.
	$sql = 'SELECT id, shortname
			FROM representatives
			WHERE chamber="house"';
	$result = $mdb2->query($sql);
	$delegates = array();
	while ($tmp = $result->fetchRow())
	{
		$delegates[$tmp{'id'}] = $tmp['shortname'];
	}
	$delegates_list = implode('@richmondsunlight.com,', $delegates);
	
	# Select 15 random screenshots not linked to an ID, getting only the screenshots of type
	# "legislator", to avoid getting the duplicate references of type "bill."
	$sql = 'SELECT video_index.id, CONCAT(files.capture_directory, video_index.screenshot, ".jpg") AS image,
			files.chamber, files.id AS file_id
			FROM video_index
			LEFT JOIN files
				ON video_index.file_id=files.id
			WHERE video_index.face_json IS NULL 
			AND video_index.linked_id IS NULL
			AND video_index.raw_text NOT LIKE "%Reverend%" AND video_index.raw_text NOT LIKE "%Clerk%"
			AND video_index.raw_text NOT LIKE "%At Ease%" AND video_index.raw_text NOT LIKE "%Adjourned%"
			AND video_index.raw_text NOT LIKE "%House%" AND video_index.raw_text NOT LIKE "%Senate%"
			AND video_index.raw_text NOT LIKE "%Jamerson%" AND video_index.raw_text NOT LIKE "%Reverend%"
			AND video_index.raw_text NOT LIKE "%Shaar%" AND video_index.raw_text NOT LIKE "%Rev. %"
			AND video_index.raw_text NOT LIKE "%Rabbi%"
			AND video_index.type="legislator"
			AND video_index.screenshot IS NOT NULL
			AND video_index.time > "00:04:00"
			ORDER BY RAND()
			LIMIT 15';
	
	$result = $mdb2->query($sql);
	
	if (PEAR::isError($result))
	{
		die($result->getMessage());
	}
	
	echo '<html>
		<head>
			<meta http-equiv=Refresh CONTENT="60; URL=http://www.richmondsunlight.com/utilities/detect_faces.php?op=detect">
		</head>
		<body>';
	
	while ($screenshot = $result->fetchRow())
	{
		
		# Get a list of all of the legislators whose names appear at any point in any chyron on
		# this video.
		$sql = 'SELECT DISTINCT representatives.shortname
				FROM representatives
				LEFT JOIN video_index
					ON representatives.id=video_index.linked_id
				LEFT JOIN files
					ON video_index.file_id=files.id
				WHERE video_index.type="legislator"
				AND video_index.file_id='.$screenshot['file_id'];

		$result2 = $mdb2->query($sql);
		
		# If there are no results, for whatever reason, just skip to the next screenshot.
		if (PEAR::isError($result2))
		{
			die($result2->getMessage());
			continue;
		}
		$uids = array();
		while ($tmp = $result2->fetchRow())
		{
			$uids[] = $tmp['shortname'];
		}
		$uids = implode('@richmondsunlight.com,', $uids).'@richmondsunlight.com';

		$url = 'http://api.face.com/faces/recognize.json?api_key='.FACE_API_KEY.'&api_secret='
		.FACE_API_SECRET.'&urls=http://www.richmondsunlight.com'.$screenshot['image']
		.'&uids='.$uids.'&detector=Aggressive&attributes=all';
		
		$json = get_content($url);
		
		/// DETECT ERROR CODES <http://developers.face.com/docs/api/return-values/>
		if ( ($json !== false) && !empty($json) )
		{
			$sql = 'UPDATE video_index
					SET face_json = "'.addslashes($json).'"
					WHERE id='.$screenshot['id'];
			$mdb2->exec($sql);
		}

		echo '<img src="'.$screenshot['image'].'" width="300" />';
		echo '<p>'.$json.'</p>';
	}
	echo '</body></html>';
}


# Storage mode
elseif ($_GET['op'] == 'store')
{
	$sql = 'SELECT id, face_json
			FROM video_index
			WHERE face_json IS NOT NULL
			AND face_json_processed = "n"
			ORDER BY RAND()
			LIMIT 1000';
	$result = $mdb2->query($sql);
	
	if (PEAR::isError($result))
	{
		die($result->getMessage());
	}
	
	while ($screenshot = $result->fetchRow())
	{
		$face_data = json_decode($screenshot['face_json']);
		$face_data = $face_data['photos'][0];
		
		# If nobody could be identified, skip this.
		if (count($face_data['tags']) == 0)
		{
			$sql = 'UPDATE video_index
					SET face_json_processed = "y"
					WHERE id='.$screenshot['id'];
			$mdb2->exec($sql);
			continue;
		}
		
		//echo '<pre>'.print_r($face_data, true).'</pre>';
		
		foreach ($face_data['tags'] as $face)
		{
			$sql = 'INSERT INTO video_index_faces
					SET video_index_id='.$screenshot['id'].', width='.$face['width'].',
					height='.$face['height'].', center="'.$face['center']['x'].','.$face['center']['y'].'",
					date_created=now()';
			if (isset($face['attributes']['mood']))
			{
				$sql .= ', mood="'.$face['attributes']['mood']['value'].'",
					mood_confidence='.$face['attributes']['mood']['confidence'];
			}
			if (isset($face['attributes']['smiling']))
			{
				$sql .= ', smiling="'.$face['attributes']['smiling']['value'].'",
					smiling_confidence='.$face['attributes']['smiling']['confidence'];
			}
			
			foreach ($face['uids'] as $legislator)
			{
				# Only bother if we have a confidence level north of 80% that the IDd legislator
				# is a match.
				if ($legislator['confidence'] < 80)
				{
					continue;
				}
				
				$tmp = explode('@', $legislator['uid']);
				$legislator['shortname'] = $tmp[0];
				$sql2 = $sql .', legislator_id=
					(SELECT id
					FROM representatives
					WHERE shortname="'.$legislator['shortname'].'"),
					confidence='.$legislator['confidence'];
				echo '<p>'.$sql2.'</p>';
				$mdb2->exec($sql2);
			}
		}
			
		$sql = 'UPDATE video_index
				SET face_json_processed = "y"
				WHERE id='.$screenshot['id'];
		$mdb2->exec($sql);
	}
}

?>