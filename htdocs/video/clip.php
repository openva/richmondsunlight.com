<?php

###
# Video Clips
# 
# PURPOSE
# Displays a given video clip.
# 
###

error_reporting(E_ALL);
ini_set('display_errors', 1);

# INCLUDES
include_once('settings.inc.php');
include_once('functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
if ( isset($_GET['hash']) && strlen($_GET['hash']) == 6 )
{
	$clip_hash = $_GET['hash'];
}

# PAGE METADATA
$page_title = 'Video » Clip';
$site_section = 'minutes';

/*
 * If we don't have a clip hash, show a list of all clips.
 */
if (!isset($clip_hash))
{
	
	$sql = 'SELECT DISTINCT SUBSTRING(MD5(video_clips.id), 1, 6) AS hash, files.path, files.date,
			DATE_FORMAT(files.date, "%b %e, %Y") AS date_formatted,
			representatives.name_formatted AS legislator_name, bills.number AS bill_number
			FROM video_clips
			LEFT JOIN files
				ON video_clips.file_id = files.id
			LEFT JOIN representatives
				ON video_clips.legislator_id = representatives.id
			LEFT JOIN bills
				ON video_clips.bill_id = bills.id
			ORDER BY files.date ASC, video_clips.time_start ASC';
	
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		
		$page_body = '<ul>';
		while ($clip = mysql_fetch_assoc($result))
		{
			
			$page_body .= '<li><a href="/video/clip/' . $clip['hash'] . '/">';
			if (!empty($clip['legislator_name']))
			{
				$page_body .= $clip['legislator_name'] . ' Speaks';
			}
			else
			{
				$page_body .= 'Legislators Speak';
			}
			
			if (!empty($clip['bill_number']))
			{
				$page_body .= ' about ' . strtoupper($clip['bill_number']);
			}
			
			if (!empty($clip['date_formatted']))
			{
				$page_body .= ' on ' . $clip['date_formatted'];
			}
			
			$page_body .= '</a></li>';
			
		}
		$page_body .= '</ul>';
	
	}
	
}

/*
 * If we have a clip hash, then show that specific clip.
 */
else
{

	$video = new Video;
	$video->hash = $clip_hash;
	if ($video->get_clip() == TRUE)
	{
		
		$page_title = ' » ' . $video->clip->title;
		
		
		$html_head = '
			<script src="/js/flowplayer-3.2.18/flowplayer-3.2.13.min.js"></script>
			<script src="/js/flowplayer/flowplayer.playlist-3.2.11.min.js"></script>';
		
		$page_body .= '
		<style>
			#video object { width: 720px; height:491px; }
			#player { width: 720px; height: 491px; }
		</style>
		<div id="video">
			
			
			<a href="' . $video->clip->path.'" style="background-image: url(' . $video->clip->screenshot
				. ');" id="player">
			<script>
				flowplayer("video", "/js/flowplayer-3.2.18/flowplayer-3.2.18.swf", {
					clip: {
						provider: "pseudostreaming",
						effect: "fade",
						fadeInSpeed: 1500,
						fadeOutSpeed: 1500
					},
					config: {
						streamingServer: "pseudostreaming"
					},
					plugins: {
						pseudostreaming: {
							url: "/js/flowplayer-3.2.18/flowplayer.pseudostreaming-3.2.13.swf"
						},
						controls: {
							playlist: true,
						}
					},
					playlist: [
						{
							url: "' . $video->clip->screenshot . '",
							duration: 0
						},
						{
							url: "' . $video->clip->path . '",
							start: ' . $video->clip->time_start_seconds . ',
							duration: ' . $video->clip->duration_seconds . '
						}
					]
				});
			</script>
			</a>
			
		</div>';
		
		
	}
	else
	{
		header("Status: 404 Not Found\n\r");
		include('../404.php');
		exit();
	}

}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->html_head = $html_head;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
