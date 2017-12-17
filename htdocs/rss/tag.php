<?php

	###
	# Bills' Activity by Tag
	# 
	# PURPOSE
	# Lists the last 20 bill actions of those patroned by a given legislator.
	#
	# NOTES
	# None.
	#
	# TODO
	# * Have die() provide an error that will appear in an RSS reader.
	# * Support If-Modified-Since and If-None-Match headers to reduce bandwidth.
	#
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once($_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php');
	include_once($_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php');
	
	# LOCALIZE VARIABLES
	$tag = urldecode($_REQUEST['tag']);
	
	# PAGE CONTENT
	
	# Check to see if there's any need to regenerate this RSS feed -- only do so
	# if it's more than a half hour old.
	if ((file_exists('cache/tag-'.$tag.'.xml')) && ((filemtime('cache/tag-'.$tag.'.xml') + 1800) > time()))
	{
		header('Content-Type: application/rss+xml');
		header('Last-Modified: '.date('r', filemtime('cache/tag-'.$tag.'.xml')));
		header('ETag: '.md5_file('cache/tag-'.$tag.'.xml'));
		readfile('cache/tag-'.$tag.'.xml');
		exit();
	}	

	# Open a database connection.
	$database = new Database;
	$database->connect_old();

	# Query the database for all bills by that tag.
	$sql = 'SELECT bills.number, bills.catch_line, bills.summary,
				(SELECT status
				FROM bills_status
				WHERE bills.id=bills_status.bill_id
				ORDER BY bills_status.date DESC, bills_status.id DESC
				LIMIT 1) AS status
			FROM bills
			LEFT JOIN tags
			ON tags.bill_id=bills.id
			WHERE bills.session_id = '.SESSION_ID.'
			AND tags.tag="'.mysql_real_escape_string($tag).'"';
	$result = mysql_query($sql);
	
	// Don't check to make sure the query was successful -- we want to make sure that people can
	// even subscribe to feeds for tags that have nothing introduced yet.
	
	$rss_content = '';
	
	# Generate the RSS.
	while ($bill = mysql_fetch_array($result))
	{
		
		# Aggregate the variables into their RSS components.
		$title = '<![CDATA['.$bill['catch_line'].' ('.strtoupper($bill['number']).')]]>';
		$link = 'http://www.richmondsunlight.com/bill/'.SESSION_YEAR.'/'.$bill['number'].'/';
		$description = '<![CDATA[<p>'.$bill['summary'].'</p><p><strong>Status: '.$bill['status'].'</strong></p>]]>';
		
		# Now assemble those RSS components into an XML fragment.
		$rss_content .= '
		<item>
			<title>'.$title.'</title>
			<link>'.$link.'</link>
			<description>'.$description.'</description>
		</item>';
	
		# Unset those variables for reuse.
		unset($item_completed);
		unset($title);
		unset($link);
		unset($description);
		
	}
	

	
	$rss = '<?xml version="1.0" encoding=\'utf-8\'?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://www.rssboard.org/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>Bills Tagged "'.$tag.'"</title>
		<link>http://www.richmondsunlight.com/bills/tags/'.$tag.'/</link>
		<description>The bills filed in the '.SESSION_YEAR.' Virginia General Assembly session that have been tagged with "'.$tag.'".</description>
		<language>en-us</language>
		'.$rss_content.'
	</channel>
</rss>';
	
	
	# Cache the RSS file.
	$fp = @file_put_contents('cache/tag-'.$tag.'.xml', $rss);
	
	header('Content-Type: application/xml');
	header('Last-Modified: '.date('r', filemtime('cache/tag-'.$tag.'.xml')));
	header('ETag: '.md5_file('cache/tag-'.$tag.'.xml'));
	echo $rss;
	
?>
