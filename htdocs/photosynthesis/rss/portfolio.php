<?php

###
# RSS: Dashboard, by Portfolio
#
# PURPOSE
# Lists the last 20 bill actions of bills for a particular portfolio.
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
include_once $_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/includes/photosynthesis.inc.php';

# LOCALIZE VARIABLES
$hash = urldecode($_REQUEST['hash']);
if (empty($hash))
{
    die('No hash found.');
}

# PAGE CONTENT

# Check to see if there's any need to regenerate this RSS feed -- only do so if it's more than
# three minutes old.
if ((file_exists('cache/portfolio-'.$hash.'.xml')) && ((filemtime('cache/portfolio-'.$hash.'.xml') + 180) > time()))
{
    header('Content-Type: application/rss+xml');
    header('Last-Modified: '.date('r', filemtime('cache/portfolio-'.$hash.'.xml')));
    header('ETag: '.md5_file('cache/portfolio-'.$hash.'.xml'));
    readfile('cache/portfolio-'.$hash.'.xml');
    exit();
}

# Open a database connection.
$database = new Database;
$database->connect_old();

# Query the database for information on this portfolio.
$sql = 'SELECT id, name
		FROM dashboard_portfolios
		WHERE hash = "'.mysql_real_escape_string($hash).'"';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0) die();
$portfolio = mysql_fetch_array($result);
$portfolio = array_map('stripslashes', $portfolio);

# Query the database for all bills in that portfolio.
$sql = 'SELECT bills.number, bills.catch_line, bills.summary,
			(SELECT status
			FROM bills_status
			WHERE bills.id=bills_status.bill_id
			ORDER BY bills_status.date DESC, bills_status.id DESC
			LIMIT 1) AS status
		FROM bills
		LEFT JOIN dashboard_bills
			ON bills.id=dashboard_bills.bill_id
		LEFT JOIN dashboard_portfolios
			ON dashboard_bills.portfolio_id = dashboard_portfolios.id
		WHERE dashboard_portfolios.id="'.$portfolio['id'].'"
		AND bills.session_id='.SESSION_ID;
$result = mysql_query($sql);

#Don't check to make sure the query was successful -- we want to make sure that people can
# even subscribe to feeds for tags that have introduced nothing yet.

$rss_content = '';

# Generate the RSS.
while ($bill = mysql_fetch_array($result))
{

    # Aggregate the variables into their RSS components.
    $title = '<![CDATA['.$bill['catch_line'].' ('.strtoupper($bill['number']).')]]>';
    $link = 'https://www.richmondsunlight.com/bill/'.SESSION_YEAR.'/'.$bill['number'].'/';
    $description = '<![CDATA[<p>'.$bill['summary'].'</p><p><strong>Status: '.$bill['status'].'</strong></p>]]>';

    # Now assemble those RSS components into an XML fragment.
    $rss_content .= '
	<item>
		<title>'.$title.'</title>
		<link>'.$link.'</link>
		<description>'.$description.'</description>
	</item>';

    # Unset those variables for reuse.
    unset($item_completed, $title, $link, $description);




}



$rss = '<?xml version="1.0" encoding=\'iso-8859-1\'?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "https://emacspeak.googlecode.com/svn/tags/release-social-dog/html/rss-0.91.dtd">
<rss version="0.91">
<channel>
	<title>'.$portfolio['name'].'</title>
	<link>http://www.richmondsunlight.com/photosynthesis/'.$hash.'/</link>
	<description>Bills you\'re tracking in the "'.$portfolio['name'].'" portfolio.</description>
	<language>en-us</language>
	'.$rss_content.'
</channel>
</rss>';


# Cache the RSS file.
$fp = @file_put_contents('cache/portfolio-'.$hash.'.xml', $rss);

header('Content-Type: application/rss+xml');
header('Last-Modified: '.date('r', filemtime('cache/portfolio-'.$hash.'.xml')));
header('ETag: '.md5_file('cache/portfolio-'.$hash.'.xml'));
echo $rss;
