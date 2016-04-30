<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
</head>
<body>
<?php

# Set a time limit of 4 minutes for this script to run.
set_time_limit(240);

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');
include_once('../includes/htmlpurifier/HTMLPurifier.auto.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

$sql = 'SELECT id, catch_line, summary
		FROM bills
		WHERE session_id != 7';
$result = mysql_query($sql);
if (mysql_num_rows($result) < 1)
{
	die('SQL failed.');
}
while ($bill = mysql_fetch_array($result))
{
	$bill = array_map('stripslashes', $bill);
	$purifier = new HTMLPurifier();
	
	# Clean up the summaries, removing the paragraph tags, newlines, and double spaces.
	$bill['summary'] = str_replace('<p>', '', $bill['summary']);
	$bill['summary'] = str_replace('</p>', '', $bill['summary']);
	$bill['summary'] = strip_tags($bill['summary'], '<b><i><strong><em>');
	$bill['summary'] = str_replace("\r", ' ', $bill['summary']);
	$bill['summary'] = str_replace("\n", ' ', $bill['summary']);
	$bill['summary'] = str_replace('  ', ' ', $bill['summary']);
	
	# Purify the HTML and trim off the surrounding whitespace.
	$bill['summary'] = trim($purifier->purify($bill['summary']));
	$bill['catch_line'] = trim($purifier->purify($bill['catch_line']));
	
	$sql = 'UPDATE bills
			SET catch_line="'.addslashes($bill['catch_line']).'",
			summary="'.addslashes($bill['summary']).'"
			WHERE id='.$bill['id'];
	mysql_query($sql);
}

?>
</body>
</html>