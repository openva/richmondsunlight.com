<?php

ini_set('display_errors', 1);
ini_set('error_reporting', 'E_ALL');
error_reporting(1);

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# Create a new instance of the Video class.
$video = new Video;

if (isset($_GET['id']))
{
	# Get a list of every file that is not currently indexed in the video_clips table.
	$sql = 'SELECT DISTINCT file_id AS id
			FROM video_index
			WHERE file_id=' . $_GET['id'];
}
else
{
	# Get a list of every file that is not currently indexed in the video_clips table.
	$sql = 'SELECT DISTINCT video_index.file_id AS id
			FROM video_index
			LEFT JOIN video_clips
				ON video_index.file_id = video_clips.file_id
			WHERE video_clips.file_id IS NULL';
}
$result = mysql_query($sql);
while ($file = mysql_fetch_array($result))
{
	$video->id = $file['id'];
	$video->store_clips();
	echo '<p>Indexed ' . $file['id'] . '.</p>';
}

?>