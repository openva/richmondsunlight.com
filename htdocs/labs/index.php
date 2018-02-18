<?php

    ###
    # Labs
    #
    # PURPOSE
    # Information about the API, experimental work, etc.
    #
    # NOTES
    # None.
    #
    # TODO
    # * Provide a ZIP file (generated on the fly?) of the images of every member of the house and
    #   senate.
    # * Add the bill XML, though not until first making sure that it's using a standard DOCTYPE.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once '../includes/settings.inc.php';
    include_once '../includes/functions.inc.php';

    # PAGE METADATA
    $page_title = 'Labs';
    $site_section = 'labs';

    # PAGE CONTENT
    $page_body = <<<EOD

		<h2>Vote CSV</h2>
		<p>Each legislator’s voting record is available, by year, as a CSV file. (No XML yet.)
		That’s found by appending <code>votes/yyyy.csv/</code> to the legislator’s page
		on Richmond Sunlight. For instance, <a href="/legislator/psticer/">Sen. Patsy Ticer’s</a>
		CSV voting data for 2008 is found at
		<code><a href="/legislator/psticer/votes/2008/csv/">http://www.richmondsunlight.com/legislator/psticer/votes/2008.csv</a></code>.
		Legislator voting CSV data provides the following, for which sample data for Sen. Ticer is
		included parenthetically:</p>

		<ul class="classic">
			<li>bill number (SB115)</li>
			<li>bill title (Traffic lights; creates Class 1 misdemeanor for running red light.)</li>
			<li>vote (Y)</li>
			<li>outcome (pass)</li>
			<li>committee (Transportation)</li>
			<li>date (2008-01-10)</li>
		</ul>

		<p>This data is updated nightly, at 2:15am. The legislature, in turn, provides Richmond
		Sunlight with voting data at 2:00am each day; we’re not holding out on you.</p>

		<h2>Bulk Downloads</h2>
		<p>An increasingly large amount of data is being provided at
		<a href="http://www.richmondsunlight.com/downloads/">http://www.richmondsunlight.com/downloads/</a>.
		This is simply a directory with files that we hope sport self-evident names. Right now you
		can download CSV files of all bills and legislators for the current session, or the full
		text of any bill (as HTML) of any bill from 2006–today. Bill text is also available on a
		per-year basis, zipped up as a single file for each year. This data is updated nightly, at
		3:00am.</p>

EOD;

    $page_sidebar = <<<EOD

		<h3>About</h3>
		<div class="box">
			<p>We’re always trying something new here at Richmond Sunlight. Before it gets
			rolled out to the public (if it makes it to the public at all), you’ll see it
			here. Everything in here is in <em>beta,</em> meaning that any feature here may change
			drastically or simply disappear without notice, so consider yourself warned.</p>
		</div>

		<h3>Twitter Updates</h3>
		<div class="box">
			<ul id="twitter_update_list"></ul>
			<p style="text-align: right;">
				<a href="http://twitter.com/richmond_sun">Follow Richmond Sunlight on Twitter</a>
			</p>
		</div>
		<script type="text/javascript" src="http://twitter.com/javascripts/blogger.js"></script>
		<script type="text/javascript" src="http://twitter.com/statuses/user_timeline/richmond_sun.json?callback=twitterCallback2&amp;count=5"></script>
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
