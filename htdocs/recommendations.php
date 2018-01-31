<?php

###
# Recommended Bills
#
# PURPOSE
# Provides a listing of bills that the user might be interested in, but hasn't seen.
#
# NOTES
# None.
#
# TODO
# None.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/functions.inc.php');
include_once('includes/settings.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# PAGE METADATA
$page_title = 'Recommended Bills';
$site_section = '';

# Include the tabbing code.
$html_head = '<script src="/js/scriptaculous/control-tabs.js" type="text/javascript"></script>';

# INITIALIZE SESSION
session_start();

# See if the user is logged in.
if (@logged_in() === false)
{
	# If the user isn't logged in, he shouldn't even be seeing this page in the first place.
	# Send him to the home page, for lack of any better idea.
	header('Location: http://www.richmondsunlight.com/');
	exit;
}

# If the user is logged in, get the user data.
else
{
	$user = @get_user();
}

$page_body = '

<div id="interests">';

$user_data = new User();
$bills = $user_data->recommended_bills();
if ($bills === false)
{
	$page_body .= '
		<p>We don’t have any bills to recommend for you just now. That might be because
		you haven’t looked at enough bills for Richmond Sunlight to get an idea of the sort
		of bills that you might be interested in, or it might because you’ve seen every
		bill that we think you might be interested in. No matter the case, check out some
		more bills on the site and then head back here—we should have some ideas for you
		then.</p>';
}
else
{
	$page_body .= '
		<h2>Based on Your Interests</h2>
		<p>These are bills that you have not looked at, but that are likely to be of
		interest to you, based on the bills that you’ve looked at on Richmond Sunlight.</p>
		<ul>';
	foreach ($bills as $bill)
	{
		$page_body .= '
			<li><a href="/bill/'.$bill['year'].'/'.$bill['number'].'/">'
			.strtoupper($bill['number']).'</a>: '.$bill['catch_line'].'</li>';
	}
	$page_body .= '
		</ul>';
}

# Close the DIV that contains this tab.
$page_body .= '</div>';

$bills = $user_data->nearby_bills();
if ($bills !== false)
{
	$page_body .= '
		<div id="location">
		<h2>Based on Your Location</h2>
		<p>These are the bills that specifically mention your region of the state.</p>
		<ul>';
	foreach ($bills as $bill)
	{
		if (strstr($bill['placename'], ' City'))
		{
			$bill['placename'] = str_replace(' City', '', $bill['placename']);
		}
		if (strstr($bill['placename'], ','))
		{
			$tmp = explode(',', $bill['placename']);
			$bill['placename'] = trim($tmp[1]);
		}

		$place[$bill{placename}][] = $bill;
	}
	ksort($place);
	foreach ($place as $name => $bills)
	{
		$page_body .= '</ul><h3>'.$name.'</h3><ul>';
		foreach ($bills as $bill)
		{
			$page_body .= '
			<li><a href="/bill/'.$bill['year'].'/'.$bill['number'].'/">'
			.strtoupper($bill['number']).'</a>: '.$bill['catch_line'].'</li>';
		}
	}
	$page_body .= '
		</ul>
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
