<?php

	###
	# About the Site
	#
	# PURPOSE
	# Basic information about this website.
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

	# PAGE METADATA
	$page_title = 'About the Site';
	$site_section = 'about';

	# PAGE CONTENT
	$page_body = <<<EOD
		<h2>Overview</h2>
		<p>Richmond Sunlight is a non-partisan website that aggregates information about the General
		Assembly, the lawmaking body that governs the state of Virginia. It is an independent,
		volunteer-run website that is in no way affiliated with the Virginia General Assembly or the
		state government.</p>

		<h2>The Data</h2>
		<p>All data about bills—titles, numbers, patrons, text, status, etc.—comes from the <a
		href="http://leg1.state.va.us/">General Assembly's Legislative Information System</a>, which
		is the General Assembly's own service.  Some of the data comes in bulk from their <a
		href="http://lis.virginia.gov/SiteInformation/ftp.html">FTP CSV service</a>, which is
		provided for services just such as Richmond Sunlight.  Most legislative data is updated
		hourly, but bill histories (the bill's progress—votes and the like) are updated daily around
		2:30pm, because that is when the General Assembly makes that data available.</p>

		<p>The remainder of the data is gathered from a variety of resources. Fundraising data comes
		from <a href="http://www.vpap.org/">the Virginia Public Access Project</a>, election history
		data comes from <a href="http://www.sbe.state.va.us/">the State Board of Elections</a>, and
		the remainder comes from a variety of resources.</p>

		<h2>Thanks To</h2>
		<ul class="classic">
			<li>Frosty Landon of the <a href="http://www.opengovva.org/">Virginia Coalition for
			Open Government</a>, for inspiration</li>
			<li>Bill Wilson and Bert Morton, of
			<a href="http://legis.state.va.us/SiteInformation/SiteInformation.htm">Division of
			Legislative Automated Systems</a>, for going above and beyond traditional expectations of
			government responsiveness</li>
			<li>David Poole, of <a href="http://www.vpap.org/">Virginia Public Access
			Project</a>, for setting a good example, and</li>
			<li>the nearly 100 people who alpha and beta tested the website, providing
			invaluable ideas, advice, and support.</li>
		</ul>

EOD;

	$page_sidebar = <<<EOD
		<div class="box">
			<h3>Words of Praise</h3>

			<p>The go-to site for tracking bills, minutes and votes...[i]t <strong>puts the General
			Assembly’s official site to shame</strong>.<br />
			–<a href="http://www.roanoke.com/editorials/wb/273845"><em>Roanoke Times</em> editorial
			board</a></p>

			<p>“...the most <strong>wonderful</strong> tool any state government could ever
			wish for... quickly becoming the semi-official guide to Virginia lawmaking...”<br
			/>
			—<a href="http://blog.washingtonpost.com/rawfisher/2008/01/blogger_of_the_month_richmond.html">Marc
			Fisher, <em>The Washington Post</em></a></p>

			<p>“...an <strong>outstanding</strong> Web site...[that] deserves credit for
			making the legislative process more public, more accessible, and more
			accountable.”<br />
			—<a href="http://hamptonroads.com/2008/01/web-removes-mystery-general-assembly-maze"><em>The
			Virginian-Pilot</em> editorial board</a></p>

			<p>“[Richmond Sunlight] has done the state <strong>a signal
			service</strong>...”<br />
			—Barton Hinkle, <em>Richmond Times-Dispatch</em></p>

			<p>“Richmond Sunlight is <strong>a great resource</strong>...”<br />
			—<a href="http://delegatekeam.org/?p=339">Del. Mark Keam (D-Vienna)</a></p>

		</div>
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
