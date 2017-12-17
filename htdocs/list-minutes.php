<?php

###
# List Minutes
# 
# PURPOSE
# Lists all available minutes.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/settings.inc.php');
include_once('includes/functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# LOCALIZE VARIABLES
if (!empty($_REQUEST['year']))
{
	$year = $_REQUEST['year'];
}
else
{
	$year = SESSION_YEAR;
}

# PAGE METADATA
$page_title = 'Minutes';
if (!empty($year))
{
	$page_title .= '» ' . $year;
}
$site_section = 'minutes';

# RETRIEVE THE MINUTES FROM THE DATABASE
$sql = 'SELECT date, chamber,
			(SELECT DATE_FORMAT(length, "%H:%i")
			FROM files
			WHERE minutes.date = files.date AND minutes.chamber = files.chamber
			AND files.type = "video" AND files.committee_id IS NULL
			LIMIT 1) AS video
		FROM minutes
		WHERE DATE_FORMAT(date, "%Y") = ' . $year . '
		ORDER BY chamber ASC, date ASC';
$result = mysql_query($sql);

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Years</h3>
		<ul>';
for ($i=2008; $i<=SESSION_YEAR; $i++)
{
	$page_sidebar .= '<li><a href="/minutes/' . $i . '/">' . $i . '</a></li>';
}
$page_sidebar .= '
		</ul>
	</div>
	
	<div class="box">
		<h3>Explanation</h3>
		<p>We have the official minutes of the General Assembly as recorded by the clerk,
		for these dates presented verbatim. “Minutes” is just a fancy term that means “list
		of stuff they did on that day” For lots of these dates we also have video and transcripts
		which are a lot more informative than the minutes.</p>
	</div>';
	
if (mysql_num_rows($result) == 0)
{
	$page_body = '<p>No minutes are yet available for ' . SESSION_YEAR . ', but you may select minutes
		from past years using the menu at right.</p>';
}
elseif (mysql_num_rows($result) > 0)
{
	
	# PAGE CONTENT
	
	# Iterate through the query results.
	while ($minutes = mysql_fetch_array($result))
	{
		$minutes = array_map('stripslashes', $minutes);
		if (!isset($chamber))
		{
			$chamber = $minutes['chamber'];
			$page_body .= '
			<div class="tabs">
			<ul>
				<li><a href="#house">House</a></li>
				<li><a href="#senate">Senate</a></li>
			</ul>
			<div id="' . $chamber . '">
				<ul id="minutes-listing">';
		}
		elseif ($chamber != $minutes['chamber'])
		{
			$chamber = $minutes['chamber'];
			$page_body .= '
				</ul>
			</div>
			<div id="'.$chamber.'">
				<ul id="minutes-listing">';
		}
		$page_body .= '<li><a href="/minutes/'.$minutes['chamber'].'/'.
			date('Y', strtotime($minutes['date'])).'/'.date('m', strtotime($minutes['date'])).'/'.
			date('d', strtotime($minutes['date'])).'/">'.date('m/d/Y', strtotime($minutes['date'])).
			'</a>'.
			(!empty($minutes['video']) ? ' with ' . $minutes['video'] . ' of video' : '').
			'</li>';
	}
	$page_body .= '
				</ul>
			</div>
		</div>';
}


# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();

?>