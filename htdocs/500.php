<?php

	###
	# 500 Error
	#
	# PURPOSE
	# Displayed when internal errors happen, which are taking place far too
	# often.
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
	include_once('includes/functions.inc.php');
	include_once('vendor/autoload.php');

	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.

	# PAGE METADATA
	$page_title = 'Server Error';
	$site_section = '';

	# PAGE CONTENT
	$page_body = <<<EOD
		<p>An internal server error has prevented your request from being processed.</p>

		<h2>Cause</h2>
		<p>It's not your fault. It's ours. Honest.  Something minor is awry with the
		server, temporarily, and it's probably OK now.</p>

		<h2>Solution</h2>
		<p>In all likelihood, simply reloading this page will cause this error to go away,
		and the page that you wanted to appear. Sorry about the trouble!</p>

EOD;

	# OUTPUT THE PAGE
	/*display_page('page_title='.urlencode($page_title).'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
		'&site_section='.urlencode($site_section));*/

	$page = new Page;
	$page->page_title = $page_title;
	$page->page_body = $page_body;
	$page->page_sidebar = $page_sidebar;
	$page->site_section = $site_section;
	$page->process();

?>
