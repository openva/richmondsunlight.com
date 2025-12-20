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
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# PAGE METADATA
$page_title = 'Site Too Busy';

# PAGE CONTENT
$page_body = '<p>We’re sorry, but the site is totally overwhelmed with traffic right now. There are more people trying to look at the site than we can show it to at once.
				Just wait a minute or two and try again, and it should likely work for you then. Sorry for the growing pains!</p>';

// OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'browser_title',
    'html_head',
    'body_tag',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
