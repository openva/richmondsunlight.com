<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('settings.inc.php');
include_once('functions.inc.php');

$log = new Log;

$sources = array(
			'house' => 'http://virginia-house.granicus.com/VPodcast.php?view_id=3',
			'senate' => 'http://virginia-senate.granicus.com/VPodcast.php?view_id=3'
			);

foreach ($sources as $chamber => $url)
{

	$cache_file = CACHE_DIR . 'video_rss_' . $chamber;

	/*
	 * Retrieve the RSS.
	 */
	$xml = get_content($url);

	if ($xml === FALSE)
	{
		echo 'RSS could not be retrieved';
		continue;
	}

	/*
	 * Turn the XML into an object.
	 */
	$xml = simplexml_load_string($xml);

	/*
	 * Get the cached GUIDs.
	 */
	$guid_cache = array();
	if (file_exists($cache_file))
	{

		$raw_cache = file_get_contents($cache_file);
		if ($raw_cache !== FALSE)
		{
			$guid_cache = unserialize($raw_cache);
		}

	}

	/*
	 * Get all GUIDs from the XML.
	 */
	$guids = array();
	foreach ($xml->channel->item as $item)
	{
		$guids[] = (string) $item->guid;
	}

	/*
	 * See which GUIDs are new.
	 */
	$new_guids = array_diff($guids, $guid_cache);

	if (count($new_guids) == 0)
	{
		continue;
	}

	/*
	 * We'll keep our new videos in this array.
	 */
	$videos = array();

	/*
	 * Iterate through each new GUID, to find it in the XML.
	 */
	foreach ($new_guids as $guid)
	{
		
		/*
		 * Iterate through each XML item, to find this GUID.
		 */
		foreach ($xml->channel->item as $item)
		{

			if ($item->guid == $guid)
			{

				/*
				 * Figure out the date of this video.
				 */
				$pos = strpos($item->title, '-');
				if ($pos === FALSE)
				{
					continue;
				}
				$date = substr($item->title, 0, $pos);
				$timestamp = strtotime($date);
				$date = date('Ymd', $timestamp);

				/*
				 * Save this video date and URL.
				 */
				$videos[$date] = (string) $item->enclosure['url'];

			}

		}

	}

	/*
	 * If we found any videos, retrieve them.
	 */
	if (count($videos) > 0)
	{

		/*
		 * Take as long as necessary.
		 */
		set_time_limit(0);

		foreach ($videos as $date => $url)
		{

			$filename = $_SERVER['DOCUMENT_ROOT'] . 'video/' . $chamber . '/floor/' . $date . '.mp4';
			if (file_exists($filename) == TRUE)
			{
				continue;
			}
			$fp = fopen($filename, 'w+');
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_exec($ch); 
			curl_close($ch);
			fclose($fp);

			$log->put('Action required: Found and stored new ' . ucfirst($chamber)
					. ' video, for ' . $date . '.', 5);

			/*
			 * Process the video.
			 */
			exec('cd ' . $_SERVER['DOCUMENT_ROOT'] . 'video/' . $chamber . '/floor/; '
				. '/vol/www/richmondsunlight.com/process-video ' . $date . ' ' . $chamber,
				$output, $status);
			if ($status === 0)
			{
				$log->put('Action required: Processed video for ' . ucfirst($chamber)
					. ' video, for ' . $date . '.', 5);
			}
			else
			{
				$log->put('Error: Could not process video for ' . ucfirst($chamber)
					. ' video, for ' . $date . '.', 5);
			}

		}

	}

	/*
	 * Write all item GUIDs back to the cache file.
	 */ 
	if (file_put_contents($cache_file, serialize($guids)) === FALSE)
	{
		echo 'Could not cache GUIDs.';
	}

}
