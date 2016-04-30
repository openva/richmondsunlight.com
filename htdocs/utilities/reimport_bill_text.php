<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<!--<meta http-equiv="refresh" content="30;url=http://www.richmondsunlight.com/utilities/reimport_bill_text.php?i=<?php echo $_GET['i']+100; ?>">-->
</head>
<body>
<?php

# Set a time limit of 4 minutes for this script to run.
error_reporting(E_ALL);
ini_set('display_errors', 1);

$i = $_GET['i'];
if (empty($i)) $i = 0;

include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');
include_once('../includes/htmlpurifier/HTMLPurifier.auto.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

$sql = 'SELECT bills_full_text.id, bills_full_text.text
		FROM bills_full_text
		WHERE id='.$_GET['id'];
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	
	# Fire up HTML Purifier.
	$purifier = new HTMLPurifier();
	
	while ($bill = mysql_fetch_array($result))
	{
		
		die(mb_detect_encoding($bill['text']));
		
		# Put the data back into the database, but clean it up first.			
		# Run the text through HTML Purifier.
		$bill['text'] = $purifier->purify($bill['text']);
		
		# We store the bill's text, and also reset the counter that tracks failed attempts
		# to retrieve the text from the legislature's website.
		$sql = 'UPDATE bills_full_text
				SET text="'.mysql_real_escape_string($bill['text']).'"
				WHERE id='.$bill['id'];
		mysql_query($sql);
		echo '<li>'.$bill['id'].'</li>';
	}
}

?>
</body>
</html>