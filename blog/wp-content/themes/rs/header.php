<?php

	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once('../includes/functions.inc.php');
	include_once('../includes/settings.inc.php');

	# PAGE METADATA
	$GLOBALS['page_title'] = 'Blog';
	if (is_single())
	{
		$GLOBALS['page_title'] .= ' '.wp_title('&raquo;', false);
	}
	
	$site_section = 'blog';

	# ADDITIONAL HTML HEADERS
	$GLOBALS['html_head'] = '<meta name="generator" content="WordPress '.get_bloginfo('version').'" />
	<link rel="stylesheet" href="'.get_bloginfo('stylesheet_url').'" type="text/css" media="screen" />
	<link rel="alternate" type="application/rss+xml" title="'.get_bloginfo('name').' RSS Feed" href="'.get_bloginfo('rss2_url').'" />
	<link rel="pingback" href="'.get_bloginfo('pingback_url').'" />';
	ob_start();
	wp_head(false);
	$GLOBALS['html_head'] .= ob_get_contents();
	ob_end_clean();

?>