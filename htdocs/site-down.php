<?php

###
# Site Down Notice
#
# PURPOSE
# Redirection page -- people pick whether they want to learn about the site
# or about the GA.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('settings.inc.php');
include_once('includes/functions.inc.php');
include_once('vendor/autoload.php');

# PAGE METADATA
$page_title = 'Site Too Busy';

# PAGE CONTENT
$page_body = '<p>We’re sorry, but the site is totally overwhelmed with traffic right now. There are more people trying to look at the site than we can show it to at once.
				Just wait a minute or two and try again, and it should likely work for you then. Sorry for the growing pains!</p>';

# OUTPUT THE PAGE

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
