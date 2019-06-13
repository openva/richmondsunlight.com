<?php

###
# Representatives Listing Page
#
# PURPOSE
# Lists all current representatives.
#
# NOTES
# None.
#
# TODO
# Reinstate the Google Maps code. The problem is that it can't handle displaying all of the
# markers. It craps out at more than ~20. The solution is to institute the Marker Manager,
# which is part of the GMaps Utility Library. But it's a pain in the ass to implement. In the
# interim, the mapping bit is just disabled.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Legislators';
$site_section = 'legislators';

# Include the tabbing code.
$html_head = '<script src="/js/scriptaculous/control-tabs.js" type="text/javascript"></script>';

# PAGE SIDEBAR
$page_sidebar = '

	<div class="box">
		<h3>Explanation</h3>
		<p>There are 100 members of the House of Delegates and 40 members of the Senate. Each
		represents the people within a single district, and those districts are numbered
		sequentially. Every Virginian is in one House district and in one Senate district, and so is
		represented by one delegate and one senator.</p>

		<p><a href="/your-legislators/">Find out who represents you in the General Assembly!</a></p>

		<p>House members serve just two-year terms, and are reelected every November in odd-numbered
		years—2013, 2015, etc. Senate members serve four-year terms, reelected in 2011, 2015,
		etc.</p>
	</div>
';

# PAGE CONTENT

# Present the tab options.
/*$page_body = '
    <ul class="tabs" id="tab_group_one">
        <li><a href="#names">Names</a></li>
        <li><a href="#location">Map</a></li>';
$page_body .= '
    </ul>';*/

$page_body .= '<div id="names">';

# Select all delegates from the database.
$sql = 'SELECT shortname, name, party, place
		FROM representatives
		WHERE chamber="house"
		AND (date_ended IS NULL OR date_ended > now())
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
	<div class="left_side">
		<h2>House of Delegates</h2>
		<ul>';
    while ($legislator = mysqli_fetch_array($result))
    {
        $legislator = array_map('stripslashes', $legislator);
        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">' . $legislator['name'] .
            ' (' . $legislator['party'] . '-' . $legislator['place'] . ')</a></li>';
    }
    $page_body .= '</ul>
		</div>';
}

# Select all senators from the database.
$sql = 'SELECT shortname, name, party, place
		FROM representatives
		WHERE chamber="senate"
		AND (date_ended IS NULL OR date_ended > now())
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '<div class="right_side">
		<h2>Senate</h2>
		<ul>';
    while ($legislator = mysqli_fetch_array($result))
    {
        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">' . $legislator['name'] .
            ' (' . $legislator['party'] . '-' . $legislator['place'] . ')</a></li>';
    }
    $page_body .= '</ul>
	</div>';
}

$page_body .= '</div>';




/*$page_body .= "\r\r\t".'<div id="location">';

# Get a listing legislators with their latitude and longitude, remembering that these are,
# bizarrely, reversed in the database.
$sql = 'SELECT id, shortname, name, chamber, latitude, longitude
        FROM representatives
        WHERE (date_ended IS NULL OR date_ended > now())
        AND latitude IS NOT NULL AND longitude IS NOT NULL';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    # Create the HTML that defines the map.
    $html_head .= "\r\t".'<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=ABQIAAAAn01L8sl4uwWn5vTPpoEoXhS0gyL4OV3haSzsE_slDr_NsupiLRSOvHSKmqYYxuXboyr-TTQzL6K8gg" type="text/javascript"></script>';

    $page_body .= '
    <div id="map" style="width: 100%; height: 300px;"></div>

    <script type="text/javascript">
        //<![CDATA[

        if (GBrowserIsCompatible()) {

            function createMarker(point,html) {
                var marker = new GMarker(point);
                GEvent.addListener(marker, "click", function() {
                    marker.openInfoWindowHtml(html);
                });
                return marker;
            }

            var map = new GMap2(document.getElementById("map"));

            map.addControl(new GSmallZoomControl());
            map.setCenter(new GLatLng(38, -79), 6);'."\r\r\t\t\t\t";

    while ($legislator = mysqli_fetch_array($result))
    {
        $legislator = array_map('stripslashes', $legislator);

        $page_body .= "\r\r
            var point = new GLatLng(".$legislator['longitude'].", ".$legislator['latitude'].");
            var marker = createMarker(point,'".pivot($legislator['name'])."')
            map.addOverlay(marker);";

    }
    $page_body .= '
        }
        //]]>
    </script>';
    $body_tag = ' onunload="GUnload()"';
}
$page_body .= '</div>';

# Insert the script code to render the tabs.
$page_body .= '
<script type="text/javascript">
    new Control.Tabs(\'tab_group_one\');
</script>';*/


# OUTPUT THE PAGE
/*display_page('page_title='.urlencode($page_title).'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
    '&site_section='.urlencode($site_section).'&body_tag='.urlencode($body_tag).'&html_head='.urlencode($html_head));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->body_tag = $body_tag;
$page->html_head = $html_head;
$page->process();
