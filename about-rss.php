<?php

	###
	# About RSS
	# 
	# PURPOSE
	# Describes the RSS feeds that are available
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
	
	# PAGE METADATA
	$page_title = 'About Subscriptions';
	$site_section = 'about';	
	
	# SIDEBAR CONTENT
	$page_sidebar = <<<EOD
	
	<div class="box">
		<h3>What is “RSS”?</h3>
		<p>RSS is a family of web feed formats used to publish frequently updated works—such
		as blog entries, news headlines, audio, and video—in a standardized format. RSS feeds
		can be read using software called an “RSS reader,” “feed reader,” or
		“aggregator,” which can be web-based or desktop-based.</p>
		
		<p>The initials “RSS” can variously be said to stand for “Really Simple
		Syndication,” “Rich Site Summary,“ and “RDF Site Summary.”</p>
		
		(<a href="http://en.wikipedia.org/wiki/RSS_%28file_format%29">From Wikipedia</a>)
	</div>
	
	<div class="box">
		<h3>How can I get started with RSS?</h3>
		<p>There are some great tutorials out there that explain how to begin:</p>
		<ul>
			<li><a href="http://www.videojug.com/film/practical-rss-introduction-to-rss-episode-1-2">A
				Practical Introduction to RSS</a> (Video)</li>
			<li><a href="http://www.youtube.com/watch?v=0klgLsSxGsU">RSS in Plain English</a>
				(Video)</li>
			<li><a href="http://news.bbc.co.uk/2/hi/help/3223484.stm">News feeds from the BBC</a>
				(The Beeb’s in-house guide)</li>
		</ul>
	</div>
	
EOD;
	
	# PAGE CONTENT
	$page_body = <<<EOD
	
	<p>There are four types of data that can be subscribed to on Richmond Sunlight using an
	aggregator. Links to activate these subscriptions are embedded within the page—your RSS reader
	should detect them. (If not, you can provide your RSS reader with the website address for the
	page to which you want to subscribe—e.g., a bill’s page or a legislator’s page—and it’ll figure
	it out.)</p>
	
	<h2>Bills Individually</h2>
	<p>This provides notification of the progress made by an individual bill. Every time it
	advances, you’ll be notified.</p>

	<p>It is <em>not</em> possible to subscribe to a bill that is dead, because what would be the
	point?</p>
	
	<h2>Bills By Tag</h2>
	<p>This provides notification of the progress mady on any bill that has been tagged with a
	particular word or phrase. Any time that any bill with that tag has any status change, that is
	reflected in the subscription. The page for every tag has a “Subscribe” box on the
	right-hand side, which is where that tag may be subscribed to.</p>

	<p>It is not possible to subscribe to a tag that does not yet exist, that is, a word or phrase
	that no bills have yet been tagged with.</p>

	<h2>Bills By Legislator</h2>
	<p>This makes it possible to follow every bill introduced by a particular legislator.  Every
	legislator’s page (e.g., <a href="/legislator/rbbell/">Del. Rob Bell</a>) has a
	“Subscribe” box on the righthand side that contains a link to subscribe to that
	legislator”s bills.  This will provide a listing of every bill that has been introduced by
	the legislator in the current session.  That listing will update whenever the status of any of
	that legislator’s bills changes.</p>
	
	<h2>Comments</h2>
	<p>Every comment posted to Richmond Sunlight about any topic.
	<a href="/rss/comments/">Subscribe now</a>!</p>
	
EOD;
	
	# OUTPUT THE PAGE
	/*display_page('page_title='.$page_title.'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
		'&site_section='.urlencode($site_section));*/

	$page = new Page;
	$page->page_title = $page_title;
	$page->page_body = $page_body;
	$page->page_sidebar = $page_sidebar;
	$page->site_section = $site_section;
	$page->process();

?>