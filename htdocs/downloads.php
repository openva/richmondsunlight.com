<?php

    ###
    # Downloads
    #
    # PURPOSE
    # A list of files available for download.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once 'includes/settings.inc.php';
    include_once 'vendor/autoload.php';

    # PAGE METADATA
    $page_title = 'Downloads';
    $site_section = '';

    # INITIALIZE SESSION
    session_start();

    # PAGE CONTENT
    $page_body = <<<EOD

		<p>In addition to <a href="https://www.richmondsunlight.com/about/api/">our API</a>, we
		offer bulk downloads of bills, bill metadata, committee data, and other datasets intended
		to facilitate reuse of our data. All data is published under a
		<a href="https://creativecommons.org/publicdomain/zero/1.0/">Creative Commons Zero</a>
		license.</p>

		<h2>Bills</h2>
		<h3>Metadata</h3>

		<h4>Description</h4>
		<p>This is data about the current session’s bills, straight from the legislature’s own
		severs, where it’s provided as CSV. We have done a simple conversion of this data to JSON,
		for ease of use on modern platforms.</p>

		<h4>Example</h4>
<pre class="code-sample"><code>{
  Bill_id: "HJ117",
  Bill_description: "Constitutional amendment (first resolution); Virginia Redistricting Commission.",
  Patron_id: "H275",
  Patron_name: "Bell, John J.",
  Last_house_committee_id: "H22",
  Last_house_action: "Committee Referral Pending",
  Last_house_action_date: "01/12/16",
  Emergency: "N",
  Passed_house: "N",
  Passed_senate: "N",
  Passed: "N",
  Failed: "N",
  Carried_over: "N",
  Approved: "N",
  Vetoed: "N",
  Full_text_doc1: "HJ117",
  Full_text_date1: "01/12/16",
  Last_house_actid: "H2201",
  Introduction_date: "01/12/16",
  Last_actid: "H2201"
}</code></pre>

		<h4>Links</h4>
		<ul>
			<li><a href="https://downloads.richmondsunlight.com/bills.csv">CSV</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills.json">JSON</a></li>
		</ul>

		<h3>Text</h3>

		<h4>Description</h4>
		<p>This is the text of all bills before the legislature, combined into a ZIP file, archived
		back to 2006. This is HTML, scraped straight from the legislature’s website.</p>

		<h4>Links</h4>
		<ul>
			<li><a href="https://downloads.richmondsunlight.com/bills/2006.zip">2006</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2007.zip">2007</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2008.zip">2008</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2009.zip">2009</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2010.zip">2010</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2011.zip">2011</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2012.zip">2012</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2013.zip">2013</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2014.zip">2014</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2015.zip">2015</a></li>
			<li><a href="https://downloads.richmondsunlight.com/bills/2016.zip">2016</a></li>
		</ul>

		<h2>Legislators</h2>

		<h3>Description</h3>
		<p>A list of all legislators currently serving in the General Assembly, with their
		name, chamber, date sworn in, party, district number, district ID, sex, email address,
		website address, place of residence, coordinates, both LIS IDs, their Richmond
		Sunlight ID, and their State Board of Elections ID.</p>

		<h3>Links</h3>
		<ul>
			<li><a href="https://downloads.richmondsunlight.com/legislators.csv">CSV</a></li>
		</ul>

		<h2>Committees</h2>

		<h3>Description</h3>
		<p>A list of all committees in each chamber, their members, and the position that they hold
		on that committee.</p>

		<h3>Example</h3>
<pre class="code-sample"><code>
{
  Senate Transportation: [
    [
      "Sen. Bill Carrico (R-Grayson)",
      "cwcarrico",
      "chair"
    ],
    [
      "Sen. Creigh Deeds (D-Bath)",
      "rcdeeds",
      "member"
    ],
    [
      "Sen. Bill DeSteph (R-Virginia Beach)",
      "wrdesteph",
      "member"
    ],
    [
      "Sen. Dave Marsden (D-Burke)",
      "dwmarsden",
      "member"
    ],
    [
      "Sen. Barbara Favola (D-Arlington)",
      "bafavola",
      "member"
    ],
    [
      "Sen. John Cosgrove (R-Chesapeake)",
      "jacosgrove",
      "member"
    ],
    [
      "Sen. Tom Garrett (R-Lynchburg)",
      "tagarrett",
      "member"
    ]
  ]
}
</code></pre>

		<h3>Links</h3>
		<ul>
			<li><a href="https://downloads.richmondsunlight.com/committees.json">JSON</a></li>
		</ul>

		<h2>Changes to Laws</h2>

		<h3>Description</h3>
		<p>These files correlate bills to the sections of the Code of Virginia that they propose to
		amend or create.</p>

		<h3>Example</h3>
<pre class="code-sample"><code>{
  bill_number: "SB287",
  bill_catch_line: "Prescription Monitoring Program; reports by dispensers shall be made within 24 hours or next day.",
  law: "54.1-2525",
  url: "http://www.richmondsunlight.com/bill/2016/sb287/"
}
</code></pre>

		<h3>Links</h3>
		<ul>
			<li><a href="https://downloads.richmondsunlight.com/law-changes.json">JSON</a>:
			Includes the bill number, bill catch line, law identifier, and the bill’s URL. This
			includes <em>only</em> the current (or most recent) session, and no historical data.</li>
			<li><a href="https://downloads.richmondsunlight.com/sections.csv">CSV</a>:
			Includes only the year, bill number, and section number affected, going back to 2006.</li>
		</ul>

		<h2>Video Index</h2>

		<h3>Description</h3>
		<p>A complete collection of all indices for all legislative session video that we have, going
		back to 2008. This is basically a series of clips—the time during which a given legislator
		spoke on the floor of the chamber, or the time during which a given bill was discussed on
		the floor of the chamber.</p>

		<h3>Example</h3>
<pre class="code-sample"><code>
{
  path: "https://archive.org/download/vahouse20090115/20090115.mp4",
  date: "2009-01-15",
  chamber: "house",
  time_start: "00:36:42",
  time_end: "00:37:12",
  legislator: "acpollard",
  bill: "HB1634"
}
</code></pre>

		<h3>Links</h3>
		<ul>
			<li><a href="https://downloads.richmondsunlight.com/video-index.json">JSON</a></li>
		</ul>

EOD;

    # OUTPUT THE PAGE
    $page = new Page();
    $page->page_title = $page_title;
    $page->page_body = $page_body;
    $page->page_sidebar = $page_sidebar;
    $page->site_section = $site_section;
    $page->process();
