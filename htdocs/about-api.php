<?php

    ###
    # About the API
    #
    # PURPOSE
    # Describes the APIs that are available
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once 'settings.inc.php';
    include_once 'vendor/autoload.php';

    # PAGE METADATA
    $page_title = 'API v1.0';
    $site_section = 'about';

    # SIDEBAR CONTENT
    $page_sidebar = <<<EOD

	<h3>What is “JSON”?</h3>
	<div class="box">
		<p>JavaScript Object Notion is a lightweight, simplified XML-like format for storing and
		exchanging data. It’s really great for pulling data across the internet from one server to
		use on another. It’s supported by every programming language under the sun, nearly all of
		which can retrieve JSON and turn it an object of data. For more information,
		<a href="http://en.wikipedia.org/wiki/JSON">read the JSON entry on Wikipedia</a>.</p>
	</div>

	<h3>How can I put this API to work?</h3>
	<div class="box">
		<p>In PHP, you’d use <a href="http://php.net/json_decode">json_decode</a>.In Perl, one of
		the <a href="http://search.cpan.org/search?query=JSON">JSON modules</a>. In Python, <a
		href="http://docs.python.org/library/json.html">json</a>. In Ruby, <a
		href="http://flori.github.com/json/">JSON</a>. In jQuery, <a
		href="http://api.jquery.com/jQuery.getJSON/">getJSON</a>.
		<a href="http://www.factsandpeople.com/facts-mainmenu-5/26-html-and-javascript/89-jquery-ajax-json-and-php">A
		brief tutorial</a> might be illustrative.</p>
	</div>

	<h3>I Still Don’t Understand</h3>
	<div class="box">
		<p>Try out our <a href="/downloads/">downloads section</a>! It offers much of the data
		provided via the API, but as single downloads in common file formats.</p>
	</div>

	<h3>Cache!</h3>
	<div class="box">
		<p>We must urge you to cache the data that you pull from Richmond Sunlight, rather than just
		serving it up live to your visitors. Richmond Sunlight lacks the server power to feed live
		data to third party websites, so when our system comes its knees, we’ll begin by cutting off
		your server’s access.</p>
	</div>

EOD;

    # PAGE CONTENT
    $page_body = <<<EOD

				<p>The v1.0 release of Richmond Sunlight’s API provides a series of JSON-based web
				services. (It also supports JSONP and its associated callback function.) The
				following methods are available:</p>

				<ul>
					<li><a href="#bill">Bill</a></li>
					<li><a href="#legislator">Legislator</a></li>
					<li><a href="#code-section">Affected Section of Code</a></li>
					<li><a href="#photosynthesis">Photosynthesis Portfolio</a></li>
				</ul>

				<h2 id="bills">Retrieve a list of bills.</h2>

				<h3>Example URI</h3>
				<p>http://api.richmondsunlight.com/1.0/bills/2011.json</p>

				<h3>Instructions</h3>
				<p>Replace the year (“2011”) with the year for which you want a listing of all
				bills.</p>

				<h3>Returns</h3>
				<ul>
					<li>number</li>
					<li>chamber</li>
					<li>date introduced</li>
					<li>status</li>
					<li>outcome</li>
					<li>title</li>
				</ul>

				<h2 id="bill">Retrieve a given bill.</h2>

				<h3>Example URI</h3>
				<p>http://api.richmondsunlight.com/1.0/bill/2010/hb1.json</p>

				<h3>Instructions</h3>
				<p>Replace the year (“2010”) and bill number (“hb1”) with the bill number and year
				in which it was introduced that you want.</p>

				<h3>Returns</h3>
				<ul>
					<li>bill number</li>
					<li>catch line</li>
					<li>summary</li>
					<li>full text</li>
					<li>sponsor</li>
					<li>outcome (passed/failed)</li>
					<li>tags (if any)</li>
				</ul>

				<p>If no bill is found, this method will return a 404.</p>


				<h2 id="legislator">Retrieve a given legislator.</h2>

				<h3>Example URI</h3>
				<p>http://api.richmondsunlight.com/1.0/legislator/rbbell.json</p>

				<h3>Instructions</h3>
				<p>Replace the legislator (“rbbell”) with the Richmond Sunlight ID for that
				legislator. (You can find each legislator’s ID in the URL for their page on the
				site.)</p>

				<h3>Returns</h3>
				<ul>
					<li>name</li>
					<li>party</li>
					<li>district number</li>
					<li>district description</li>
					<li>date took office</li>
					<li>partisanship (0-100 scale; 0 == Democratic, 100 == Republican)</li>
					<li>all bills (since 2006)
						<ul>
							<li>number</li>
							<li>year</li>
							<li>title</li>
							<li>date introduced</li>
							<li>outcome</li>
							<li>Richmond Sunlight URL</li>
						</ul>
					</li>
					<li>district office address</li>
					<li>capitol office address</li>
					<li>district office phone number</li>
					<li>capitol office phone number</li>
					<li>website URI (if any)</li>
					<li>e-mail address</li>
					<li>Virginia Public Access Project ID</li>
					<li>Legislative Information System ID</li>
				</ul>

				<p>If no legislator is found, this method will return a 404.</p>


				<h2 id="code-section">Retrieve a list of bills that affect a specific section of the
				code.</h2>

				<h3>Example URI</h3>
				<p>http://api.richmondsunlight.com/1.0/bysection/20-107.3.json</p>

				<h3>Instructions</h3>
				<p>Replace the section number with the section that you’re interested in. It will return
				a list of bills (from 2006 foreward) that cite that section number in the text of the
				bill.</p>

				<h3>Returns</h3>
				<ul>
					<li>bill number</li>
					<li>year</li>
					<li>catch line</li>
					<li>summary</li>
					<li>sponsor</li>
					<li>outcome (passed/failed)</li>
				</ul>

				<p>If no bills are found, this method will return a 404.</p>



				<h2 id="photosynthesis">Retrieve a list of bills and commentary on those bills for a
				given Photosynthesis portfolio.</h2>

				<h3>Example URI</h3>
				<p>http://api.richmondsunlight.com/1.0/photosynthesis/7l0x3.json</p>

				<h3>Instructions</h3>
				<p>Replace the portfolio number (“7l0x3”) with the number of the portfolio that
				you’re interested in. (You can find this in the URL.) It will return a list of the
				bills in that portfolio, along with any commentary that has been provided on each
				of those bills.</p>

				<h3>Returns</h3>
				<ul>
					<li>bill number</li>
					<li>year</li>
					<li>URL</li>
					<li>notes</li>
				</ul>

				<p>If no bills is found for that portfolio, or if the portfolio doesn’t exist, this
				method will return a 404.</p>

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
