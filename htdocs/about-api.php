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
    $page_title = 'API v1.1';
    $site_section = 'about';

    # SIDEBAR CONTENT
    $page_sidebar = <<<EOD

	<h3>What is "JSON"?</h3>
	<div class="box">
		<p>JSON is a lightweight format for exchanging data. It's supported by every
		programming language and is ideal for retrieving structured data from a server.
		For more information, <a href="https://en.wikipedia.org/wiki/JSON">read the JSON
		entry on Wikipedia</a>.</p>
	</div>

	<h3>How can I put this API to work?</h3>
	<div class="box">
		<p>In PHP, use <a href="https://www.php.net/json_decode">json_decode</a>. In Python,
		use the built-in <a href="https://docs.python.org/3/library/json.html">json</a>
		module. In JavaScript, use <a href="https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API">fetch</a>
		or any HTTP library.</p>
	</div>

	<h3>I Still Don't Understand</h3>
	<div class="box">
		<p>Try our <a href="/downloads/">downloads section</a>, which offers much of the
		same data as single-file downloads in common formats.</p>
	</div>

	<h3>Please Cache!</h3>
	<div class="box">
		<p>Please cache the data you retrieve from Richmond Sunlight rather than serving it
		live to your visitors. When our servers come under strain, we will begin cutting off
		access from high-volume third-party consumers.</p>
	</div>

	<h3>Machine-Readable Spec</h3>
	<div class="box">
		<p>A full <a href="https://api.richmondsunlight.com/openapi.yaml">OpenAPI 3.0
		specification</a> is available for programmatic use.</p>
	</div>

EOD;

    # PAGE CONTENT
    $page_body = <<<EOD

	<p>The Richmond Sunlight API v1.1 provides JSON access to Virginia legislative data.
	All endpoints are served from <code>https://api.richmondsunlight.com/1.1/</code>.
	All responses are JSON. The following endpoints are available:</p>

	<ul>
		<li><a href="#bills">List of bills</a></li>
		<li><a href="#bill">Bill detail</a></li>
		<li><a href="#legislators">List of legislators</a></li>
		<li><a href="#legislator">Legislator detail</a></li>
		<li><a href="#vote">Vote detail</a></li>
		<li><a href="#bysection">Bills by code section</a></li>
		<li><a href="#section-video">Video clips by code section</a></li>
		<li><a href="#videos">List of videos</a></li>
		<li><a href="#video">Video detail</a></li>
		<li><a href="#tag-suggest">Tag suggestions</a></li>
	</ul>


	<h2 id="bills">List of bills</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/bills/2025.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the year with the four-digit session year for which you want a listing of
	all bills.</p>

	<h3>Returns</h3>
	<ul>
		<li>number</li>
		<li>chamber</li>
		<li>date introduced</li>
		<li>status</li>
		<li>outcome</li>
		<li>title</li>
		<li>patron (name and ID)</li>
	</ul>


	<h2 id="bill">Bill detail</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/bill/2025/hb1.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the year and bill number with the bill you want. Bill numbers must be
	lowercase (e.g., <code>hb41</code>, <code>sb100</code>). Supported prefixes are
	<code>hb</code> (House Bill), <code>sb</code> (Senate Bill), <code>hj</code>
	(House Joint Resolution), <code>sj</code> (Senate Joint Resolution),
	<code>hr</code> (House Resolution), and <code>sr</code> (Senate Resolution).</p>

	<h3>Returns</h3>
	<ul>
		<li>number</li>
		<li>catch line</li>
		<li>summary</li>
		<li>status</li>
		<li>outcome</li>
		<li>patron</li>
		<li>associated video clips (URL, screenshot, start/end time)</li>
	</ul>

	<p>Returns 404 if no bill is found.</p>


	<h2 id="legislators">List of legislators</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/legislators.json</code></p>
	<p><code>https://api.richmondsunlight.com/1.1/legislators.json?year=2025</code></p>

	<h3>Instructions</h3>
	<p>Returns all current legislators. Optionally filter by session year using the
	<code>year</code> query parameter.</p>

	<h3>Returns</h3>
	<ul>
		<li>id</li>
		<li>name</li>
		<li>chamber (<code>house</code> or <code>senate</code>)</li>
		<li>party</li>
		<li>district</li>
	</ul>


	<h2 id="legislator">Legislator detail</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/legislator/rcdeeds.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the shortname with the legislator's Richmond Sunlight identifier, which
	appears in the URL on their page (e.g., <code>/legislator/rcdeeds/</code>).
	Shortnames are lowercase and may contain hyphens.</p>

	<h3>Returns</h3>
	<ul>
		<li>id</li>
		<li>name</li>
		<li>chamber</li>
		<li>party</li>
		<li>district</li>
		<li>State Board of Elections ID (<code>sbe_id</code>)</li>
		<li>all bills sponsored, with number, title, date introduced, outcome, and URL</li>
	</ul>

	<p>Returns 404 if no legislator is found.</p>


	<h2 id="vote">Vote detail</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/vote/2025/1.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the year and LIS vote ID number with the vote you want. The LIS ID is a
	numeric identifier (1–6 digits) found in the vote URL on this site.</p>

	<h3>Returns</h3>
	<ul>
		<li>chamber</li>
		<li>outcome</li>
		<li>tally</li>
		<li>per-legislator record: name, shortname, vote, party, chamber, district, address, date started</li>
	</ul>

	<p>Returns 404 if no vote is found.</p>


	<h2 id="bysection">Bills by code section</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/bysection/18.2-174.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the section number (URL-encoded if it contains special characters) with
	the Virginia Code section you want. Returns all bills from 2006 forward that cite
	that section in their text.</p>

	<h3>Returns</h3>
	<ul>
		<li>year</li>
		<li>number</li>
		<li>catch line</li>
		<li>summary</li>
		<li>outcome</li>
		<li>chamber</li>
		<li>legislator</li>
	</ul>

	<p>Returns 404 if no bills are found.</p>


	<h2 id="section-video">Video clips by code section</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/section-video/18.2-174.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the section number with the Virginia Code section you want. Returns video
	clips from legislative proceedings that discuss that section.</p>

	<h3>Returns</h3>
	<ul>
		<li>bill number</li>
		<li>year</li>
		<li>date</li>
		<li>chamber</li>
		<li>time start/end</li>
		<li>screenshot URL</li>
		<li>video URL</li>
	</ul>

	<p>Returns 404 if no clips are found.</p>


	<h2 id="videos">List of videos</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/videos.json</code></p>
	<p><code>https://api.richmondsunlight.com/1.1/videos.json?year=2025</code></p>

	<h3>Instructions</h3>
	<p>Returns all legislative videos. Optionally filter by year using the
	<code>year</code> query parameter.</p>

	<h3>Returns</h3>
	<ul>
		<li>id</li>
		<li>date</li>
		<li>chamber</li>
		<li>committee</li>
		<li>title</li>
		<li>path</li>
		<li>description</li>
		<li>width/height</li>
		<li>sponsor</li>
		<li>has_transcript</li>
		<li>is_indexed</li>
	</ul>


	<h2 id="video">Video detail</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/video/123.json</code></p>

	<h3>Instructions</h3>
	<p>Replace the numeric ID with the ID of the video you want.</p>

	<h3>Returns</h3>
	<p>Same fields as the videos list above.</p>

	<p>Returns 404 if no video is found.</p>


	<h2 id="tag-suggest">Tag suggestions</h2>

	<h3>Example URI</h3>
	<p><code>https://api.richmondsunlight.com/1.1/tag-suggest?term=crime</code></p>

	<h3>Instructions</h3>
	<p>Provide a partial tag string via the <code>term</code> query parameter. Returns
	matching tag names for use in autocomplete or tag lookup.</p>

	<h3>Returns</h3>
	<p>Array of matching tag name strings (e.g., <code>["crime", "firearm",
	"weapon"]</code>).</p>

	<p>Returns 404 if no tags match.</p>

EOD;

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
