<?php

###
# Unsubscribe
# 
# PURPOSE
# Lets people end their comments subscription. They have the ability to be e-mailed every time
# somebody posts a comment to a bill that they're following -- this terminates that. Or, rather,
# this terminates e-mails about that one bill. It doesn't end all of their subscriptions.
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
include_once('includes/settings.inc.php');
include_once('functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# LOCALIZE VARIABLES
if ( isset($_GET['hash']) && (strlen($_GET['hash']) == 8) )
{
	$hash = $_GET['hash'];
}
else
{
	die('Invalid unsubscribe link.');
}

# PAGE METADATA
$page_title = 'Unsubscribe';
$site_section = '';

# PAGE CONTENT

# Terminate the subscription.
$sql = 'DELETE FROM comments_subscriptions
		WHERE hash="'.mysql_real_escape_string($hash).'"
		LIMIT 1';
mysql_query($sql);

# If we've got a referer, get the domain for us to figure out where to send this person.
if (isset($_SERVER['HTTP_REFERER']))
{
	$url = parse_url($_SERVER['HTTP_REFERER']);
}

# If we have a local referer, return the person to that URL
if ($url['host'] == $_SERVER['SERVER_NAME'])
{
	header('Location: '.$_SERVER['HTTP_REFERER']);
	exit();
}

# But if we don't have a local referer, display a message, acknowledging that the subscription is
# terminated.
$page_body .= '<p>You have been unsubscribed.</p>';
	
# OUTPUT THE PAGE
/*display_page('page_title='.urlencode($page_title).'&page_body='.urlencode($page_body).
	'&page_sidebar='.urlencode($page_sidebar).'&site_section='.urlencode($site_section));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();

?>