<?php

###
# Legislator Page
#
# PURPOSE
# Information about each legislator.
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

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
$shortname = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['shortname']);

# Get the legislator's info. from the API.
# We append a query string, containing the current time, to avoid getting a cached copy.
$json_url = API_URL . '1.1/legislator/' . $shortname . '.json?' . time();
$json = get_content($json_url);

$debug_timing['JSON retrieved'] = microtime(TRUE);

if ($json === FALSE)
{
    header("Status: 404 Not Found\n\r");
    include '404.php';
    exit();
}

$legislator = json_decode($json);

# Cast this bill as an array, rather than an object, in which the array is wrapped as a result of
# being stored in JSON.
$legislator = (array) $legislator;

# Externally, we call the shortname the "id," so rename that so it'll make sense here.
$legislator['id'] = $legislator['rs_id'];

# Create a new video object.
$video = new Video();

# Get a list of videos for this legislator.
$video->legislator_id = $legislator['id'];
$legislator['videos'] = $video->legislator_sample();

# Gin up a meta description for search engines.
$html_head = '
<meta name="description" content="Information about ' . $legislator['name_formatted']
    . ', including a list of ' . $legislator['possessive'] . ' bills, ' . $legislator['possessive']
    . ' full voting record, contact information, donors, recent media coverage, and more." />';

# PAGE METADATA
$page_title = $legislator['name_formatted'];
$site_section = 'legislators';

/*
 * Twitter metadata.
 */
$html_head .= '<meta name="twitter:card" content="summary" />
	<meta property="twitter:title" content="' . $legislator['name_formatted'] . ' "/>
	<meta property="twitter:image" content="https://www.richmondsunlight.com/images/legislators/medium/'
        . $legislator['shortname'] . '.jpg"/>
	<meta property="twitter:description" content="Representing Virginia ' . ucfirst($legislator['chamber'])
        . ' District ' . $legislator['district'] . ', including ' . $legislator['district_description'] . ', '
        . 'since ' . date('Y', strtotime($legislator['date_started'])) . '." />';

# PAGE SIDEBAR
$page_sidebar = '';

# Contact the rep.
$page_sidebar .= '
<div class="box vcard">
	<h3>Contact ' . $legislator['name'] . '</h3>
	<span style="display: none;" class="fn">' . $legislator['name'] . '</span>';

# District Office
if (!empty($legislator['address_district']))
{
    $page_sidebar .= '
	<p class="adr"><strong>District Office</strong><br />
	' . $legislator['address_district'];
}
if (!empty($legislator['phone_district']))
{
    $page_sidebar .= '<br /><span class="tel">' . $legislator['phone_district'] . '</span>';
}
$page_sidebar .= '</p>';

# Richmond Office
$page_sidebar .= '
	<p class="adr"><strong>Richmond Office</strong> (during session)<br />
	P.O. Box ';
if ($legislator['chamber'] == 'house')
{
    $page_sidebar .= '406';
}
elseif ($legislator['chamber'] == 'senate')
{
    $page_sidebar .= '396';
}
$page_sidebar .= '<br />
	Richmond, Virginia 23218';
if (!empty($legislator['phone_richmond']))
{
    $page_sidebar .= '<br /><span class="tel">' . $legislator['phone_richmond'] . '</span>';
}
$page_sidebar .= '</p>';
if (!empty($legislator['address_richmond']))
{
    $page_sidebar .= '
	<p>Room ' . $legislator['address_richmond'] . ' of the General Assembly Building</p>';
}

# E-Mail
if (!empty($legislator['email']))
{
    $page_sidebar .= '
	<p><strong>Email</strong><br />
	<a href="mailto:' . spam_proof($legislator['email']) . '" class="email">' . spam_proof($legislator['email']) . '</a></p>';
}

/*
 * Display a map of the district boundaries.
 */

if ($legislator['district_boundaries'] != FALSE)
{

    /*
     * Pull out the relevant bit from the GeoJSON
     */
    $legislator['district_boundaries'] = json_decode($legislator['district_boundaries']);
    unset($legislator['district_boundaries']->features[0]->properties);
    $legislator['district_boundaries'] = $legislator['district_boundaries']->features[0];
    $legislator['district_boundaries'] = json_encode($legislator['district_boundaries']);

    $html_head .= '<script src="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.css" rel="stylesheet" />
    <script src="https://npmcdn.com/@turf/turf/turf.min.js"></script>
        <style>
            #district_map { height: 250px; }
        </style>
        <script>
            $( document ).ready(function() {

                mapboxgl.accessToken = "' . MAPBOX_TOKEN . '";
                var map = new mapboxgl.Map({
                    container: "district_map",
                    style: "mapbox://styles/mapbox/streets-v11",
                    center: [-78.57,37.48],
                    zoom: 5
                });

                var geojsonData = ' . $legislator['district_boundaries'] . '

                map.on("load", function() {
                    map.addSource("boundaries", {
                        "type": "geojson",
                        "data": geojsonData
                        }
                    );
                    map.addLayer({
                        "id": "boundaries",
                        "type": "line",
                        "source": "boundaries",
                        "layout": {
                            "line-join": "round",
                            "line-cap": "round"
                        },
                        "paint": {
                            "line-color": "#888",
                            "line-width": 8
                        }
                    });

                    var center = turf.center(geojsonData);
                    map.flyTo({
                        center: center.geometry.coordinates,
                        essential: true
                    });
                
                });

            });
        </script>';

    $page_sidebar .= '<div id="district_map"></div>';
}

$page_sidebar .= '
</div>';

# Newest Comments
$sql = 'SELECT comments.id, comments.bill_id, comments.date_created AS date,
		comments.name, comments.email, comments.url, comments.comment,
		comments.type, bills.number AS bill_number, bills.catch_line AS bill_catch_line,
		sessions.year,
			(
			SELECT COUNT(*)
			FROM comments
			WHERE bill_id=bills.id AND status="published"
			AND date_created <= date
			) AS number
		FROM comments
		LEFT JOIN bills
		ON bills.id=comments.bill_id
		LEFT JOIN sessions
		ON bills.session_id=sessions.id
		WHERE comments.status="published" AND bills.chief_patron_id=' . $legislator['id'] . '
		ORDER BY comments.date_created DESC
		LIMIT 5';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_sidebar .= '
		<div class="box" id="newest-comments">
			<h3>Newest Comments</h3>
			<ul>';
    while ($comment = mysqli_fetch_array($result))
    {
        $comment = array_map('stripslashes', $comment);
        if (mb_strlen($comment['comment']) > 175)
        {
            $comment['comment'] = preg_replace('#<blockquote>(.*)</blockquote>#D', '', $comment['comment']);
            $comment['comment'] = strip_tags($comment['comment']);
            $comment['comment'] = mb_substr($comment['comment'], 0, 120) . '...';
        }
        $page_sidebar .= '
				<li style="margin-bottom: .75em;"><strong>' . mb_strtoupper($comment['bill_number']) . ': ' . $comment['bill_catch_line'] . '</strong><br />
				<a href="/bill/' . $comment['year'] . '/' . $comment['bill_number'] . '/#comment-' . $comment['number'] . '">' . $comment['name'] . ' writes:</a>
				' . $comment['comment'] . '</li>';
    }
    $page_sidebar .= '
			</ul>
		</div>';
}


# Voting Record
$page_sidebar .= '
	<div class="box">
		<h3>Voting Record</h3>
		' . $legislator['prefix'] . ' ' . $legislator['name'] . '’s voting record for ';

# Figure out when to start listing years. We don't have voting data prior to 2006.
if (!empty($legislator['year_started']))
{
    if ($legislator['year_started'] < 2006)
    {
        $start = 2006;
    }
    else
    {
        $start = $legislator['year_started'];
    }
}
else
{
    $start = 2006;
}

# Figure out when to stop listing years.
if (!empty($legislator['year_ended']))
{
    $end = $legislator['year_ended'];
}
else
{
    $end = date('Y');
}

# Iterate through the years and provide links.
for ($i=$start; $i<=$end; $i++)
{
    $page_sidebar .= '<a href="/legislator/' . $legislator['shortname'] . '/votes/' . $i . '/">'
        . $i . '</a>';
    if (($i + 1) == $end)
    {
        $page_sidebar .= ', and ';
    }
    elseif ($i != $end)
    {
        $page_sidebar .= ', ';
    }
}
$page_sidebar .= ' is available to view or download.
	</div>';


# Corrections
$page_sidebar .= '
	<div class="box">
		<h3>Corrections? Additions?</h3>
		If any information about ' . $legislator['name'] . ' is missing, incomplete or wrong,
		please <a href="/contact/">correct it</a>.
	</div>';

# PAGE CONTENT
$page_body = '
<div class="tabs">
<ul class="tabs">
	<li><a href="#bio">Bio</a></li>';
$page_body .= '
	<li><a href="#media">Media</a></li>';
if (!empty($legislator['rss_url']))
{
    $page_body .= '
	<li><a href="#news">News</a></li>';
}
if (!empty($legislator['videos']))
{
    $page_body .= '
	<li><a href="#video">Video</a></li>';
}
$page_body .= '
</ul>

<div id="bio">';

# Get the batting average data.  Use the current session's year if the session
# is finished.  Otherwise, use the prior year.
if (IN_SESSION == 'Y')
{
    $batting_year = SESSION_YEAR - 1;
}
else
{
    $batting_year = SESSION_YEAR;
}
$sql = 'SELECT COUNT(*) AS passed,
		(
			SELECT COUNT(*)
			FROM bills
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id
			WHERE sessions.year = ' . $batting_year . '
            AND chief_patron_id = ' . $legislator['id'] . '
            AND
                (bills.number LIKE "hb%"
                OR
                bills.number LIKE "sb%")
		) AS total
		FROM bills
            LEFT JOIN representatives
                ON bills.chief_patron_id = representatives.id
            LEFT JOIN sessions
                ON bills.session_id = sessions.id
		WHERE sessions.year = ' . $batting_year . '
        AND chief_patron_id = ' . $legislator['id'] . '
        AND (bills.outcome = "passed")
        AND
            (bills.number LIKE "hb%"
            OR
            bills.number LIKE "sb%")';
$result = mysqli_query($GLOBALS['db'], $sql);
$legislator['batting'] = mysqli_fetch_array($result);
if ($legislator['batting']['total'] == 0)
{
    unset($legislator['batting']);
}

$page_body .= '
	<img src="/images/legislators/thumbnails/' . $legislator['shortname'] . '.jpg" alt="Photo of '
        . $legislator['name'] . '" width="150" id="legislator" />
	<dl>
		<dt>Party</dt>
		<dd>' . $legislator['party_name'] . '</dd>
		<dt>District</dt>
		<dd>' . $legislator['district'] . ': ' . $legislator['district_description'] . '</dd>';
if ($legislator['date_started'] != '0000-00-00')
{
    $page_body .= '
		<dt>Took Office</dt>
		<dd>' . $legislator['date_started'] . '</dd>';
}
if (!empty($legislator['date_ended']))
{
    $page_body .= '
		<dt>Left Office</dt>
		<dd>' . $legislator['date_ended'] . '</dd>';
}
else
{
    $elections = [];
    $elections['house'] = [2025, 2027, 2029, 2031, 2033, 2035, 2037, 2039];
    $elections['senate'] = [2027, 2031, 2035, 2039];
    if ($legislator['chamber'] == 'house')
    {
        foreach ($elections['house'] as $election)
        {
            if (strtotime($election. '-11-15') > time())
            {
                $next_election = 'November ' . $election;
                break;
            }
        }
        
    }
    elseif ($legislator['chamber'] == 'senate')
    {
        foreach ($elections['senate'] as $election)
        {
            if (strtotime($election. '-11-15') > time())
            {
                $next_election = 'November ' . $election;
                break;
            }
        }
    }
    $page_body .= '
		<dt>Next Election</dt>
		<dd>' . $next_election . '</dd>';
}
if (is_array($legislator['committees']) && (count($legislator['committees']) > 0))
{
    $page_body .= '
		<dt>Committees</dt>
		<dd>';
    $i=0;
    foreach ($legislator['committees'] as $committee)
    {
        $page_body .= '<a href="/committee/' . $legislator['chamber'] . '/'
            . $committee->shortname . '/">' . $committee->name . '</a>';
        if ($committee->position == 'chair')
        {
            $page_body .= ' (Chair)';
        }
        elseif ($committee->position == 'vice chair')
        {
            $page_body .= ' (Vice Chair)';
        }
        if ($i < count($legislator['committees'])-1)
        {
            $page_body .= ', ';
        }
        $i++;
    }
    $page_body .= '</dd>';
}
if (($legislator['age'] != date('Y')) && !empty($legislator['age']))
{
    $page_body .= '<dt>Age</dt>
		<dd>' . $legislator['age'] . '</dd>';
}
if (!empty($legislator['website']))
{
    $page_body .= '
		<dt>Website</dt>
		<dd><a href="' . $legislator['website'] . '">' . $legislator['website_name'] . '</a></dd>';
}
if (!empty($legislator['twitter']))
{
    $page_body .= '
		<dt>Twitter</dt>
		<dd><a href="https://twitter.com/' . $legislator['twitter'] . '">@' . $legislator['twitter'] . '</a></dd>';
}

if (!empty($legislator['activity']) && IN_SESSION == 'Y')
{
    $page_body .= '
		<dt>Daily Activity</dt>
		<dd id="activity">
			<img src="'
            . '//chart.googleapis.com/chart?cht=ls&chs=400x70&chco=243a51&chf=bg,s,f4eee5'
            . '&chm=B,dccbaf,0,0,0&chds=0,' . $legislator['activity_peak'] . '&chd=t:'
            . ($legislator['activity']) . '" />
		</dd>';
}

# COPATRONING STATS
# Calculate the percentage of the bills copatroned by this legislator that were introduced by
# each party.
$sql = 'SELECT representatives.party, COUNT(*) AS number
		FROM bills_copatrons
		LEFT JOIN bills
			ON bills_copatrons.bill_id=bills.id
		LEFT JOIN representatives
			ON bills.chief_patron_id=representatives.id
		WHERE bills_copatrons.legislator_id=' . $legislator['id'] . '
		GROUP BY representatives.party';

$result = mysqli_query($GLOBALS['db'], $sql);
$tmp = array();
while ($copatron = mysqli_fetch_array($result))
{
    $tmp[$copatron{'party'}] = $copatron['number'];
}
$total = array_sum($tmp);
if ($total > 0)
{
    arsort($tmp);

    # Create the text that we'll use below in the copatroning stats.
    $introduced = round((current($tmp)/$total)*100) . '% of bills ' . $legislator['pronoun']
    . ' copatroned were introduced by ' . ((key($tmp)=='R') ? 'Republicans' : 'Democrats') . '. ';

    # Populate an array that we use to determine overall partisanship. 0 = Democratic and 100 =
    # Republican. Because our number is based on the majority support, we need to rescale it.
    if (key($tmp)=='D')
    {
        $tmp = round((current($tmp)/$total)*100);
        if ($tmp > 50)
        {
            $tmp = 50 - ($tmp - 50);
        }
    }
    else
    {
        $tmp = round((current($tmp)/$total)*100);
    }
}

# Calculate the percentages of the legislators' party memberships who have cosponsored any bill
# introduced by this legislator.
// Using this "IN" clause is just ridiculous. The query takes a good .2 seconds, which is way
// too long. There's got to be a faster way to do this.
$sql = 'SELECT representatives.party, COUNT(*) AS number
		FROM bills_copatrons
			LEFT JOIN representatives
				ON bills_copatrons.legislator_id = representatives.id
		WHERE bills_copatrons.bill_id
		IN
			(SELECT id
			FROM bills
			WHERE chief_patron_id = ' . $legislator['id'] . ')
		GROUP BY representatives.party';
$result = mysqli_query($GLOBALS['db'], $sql);
$tmp = array();
while ($copatron = mysqli_fetch_array($result))
{
    $tmp[$copatron{'party'}] = $copatron['number'];
}
$total = array_sum($tmp);
if ($total > 0)
{
    arsort($tmp);

    # Create the text that we'll use below in the copatroning stats.
    $supporters = 'Of all of the copatrons of ' . (($legislator['sex'] == 'male') ? 'his' : 'her')
        . ' bills, ' . round((current($tmp)/$total)*100) . '% of them are '
    . ((key($tmp)=='R') ? 'Republicans' : 'Democrats') . '. ';

    # Populate an array that we use to determine overall partisanship. 0 = Democratic and 100 =
    # Republican. Because our number is based on the majority support, we need to rescale it.
    if (key($tmp)=='D')
    {
        $tmp = round((current($tmp)/$total)*100);
        if ($tmp > 50)
        {
            $tmp = 50 - ($tmp - 50);
        }
    }
    else
    {
        $tmp = round((current($tmp)/$total)*100);
    }
}

# Calculate the percentages of the legislators' party memberships who are in the overall pool
# of bills copatroned by this legislator. Meaning, look at every bill that this legislator has
# copatroned, and look at every other copatron of those bills, and calculate the percentage of
# those copatrons that are Democrats, Republicans, and independents.
// Using this "IN" clause is just ridiculous. The query takes a good .1 seconds, which is way
// too long. There's got to be a faster way to do this.
$sql = 'SELECT representatives.party, COUNT(*) AS number
		FROM bills_copatrons
			LEFT JOIN representatives
				ON bills_copatrons.legislator_id=representatives.id
		WHERE
			bills_copatrons.bill_id IN
				(SELECT bill_id
				FROM bills_copatrons
				WHERE legislator_id=' . $legislator['id'] . ')
		GROUP BY representatives.party';
$result = mysqli_query($GLOBALS['db'], $sql);
$tmp = array();
while ($copatron = mysqli_fetch_array($result))
{
    $tmp[$copatron{'party'}] = $copatron['number'];
}
$total = array_sum($tmp);
if ($total > 0)
{
    arsort($tmp);
    # Create the text that we'll use below in the copatroning stats.
    $pool = 'Of all of the copatrons of all of the bills that '
        . (($legislator['sex'] == 'male') ? 'he' : 'she') . ' also copatroned, '
        . round((current($tmp)/$total)*100) . '% of them are '
    . ((key($tmp)=='R') ? 'Republicans' : 'Democrats') . '. ';

    # Populate an array that we use to determine overall partisanship. 0 = Democratic and 100 =
    # Republican. Because our number is based on the majority support, we need to rescale it.
    if (key($tmp)=='D')
    {
        $tmp = round((current($tmp)/$total)*100);
        if ($tmp > 50)
        {
            $tmp = 50 - ($tmp - 50);
        }
    }
    else
    {
        $tmp = round((current($tmp)/$total)*100);
    }
}


# Display how partisan that this legislator's record is, in light of his copatroning habits.
# We've calculated these copatroning habits via a cron job already.
if (!empty($legislator['partisanship']))
{
    $partisanship = '
		<div id="partisanship-graph">
			<div style="width: ' . $legislator['partisanship'] . '%;"></div>
		</div>';
}

if (isset($introduced) || isset($supporters) || isset($pool))
{
    $page_body .= '<dt>Copatroning Habits</dt>
			<dd id="copatron">';
    if (isset($introduced))
    {
        $page_body .= $introduced;
    }
    if (isset($supporters))
    {
        $page_body .= $supporters;
    }
    if (isset($pool))
    {
        $page_body .= $pool;
    }
    $page_body .= '</dd>';
}
if (isset($partisanship))
{
    $page_body .= '
			<dt>Partisanship</dt>
			<dd id="partisanship">
			' . $partisanship . '   <a href="javascript:openpopup(\'/help/partisanship/\')" title="Help"><img src="/images/help-f4eee5.gif" class="help-icon" alt="?" /></a>
			</dd>';
}

# Tag Cloud
$sql = 'SELECT COUNT(*) AS count, tags.tag
		FROM tags
		LEFT JOIN bills
			ON tags.bill_id = bills.id
		LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
		WHERE representatives.id = ' . $legislator['id'] . '
		GROUP BY tags.tag
		ORDER BY count DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
$tag_count = mysqli_num_rows($result);
if ($tag_count > 0)
{
    $page_body .= '
		<dt>Tag Cloud <a href="javascript:openpopup(\'/help/tag-clouds/\')" title="Help"><img src="/images/help-f4eee5.gif" class="help-icon" alt="?" /></a></dt>
		<dd>
			<div class="tags">';
    # Build up an array of tags, with the key being the tag and the value being the count.
    while ($tag = mysqli_fetch_array($result))
    {
        $tag = array_map('stripslashes', $tag);
        $tags[$tag{'tag'}] = $tag['count'];
    }

    # Sort the tags in reverse order by key (their count), shave off the top 30, and then
    # resort alphabetically.
    arsort($tags);
    $tags = array_slice($tags, 0, 30, TRUE);
    ksort($tags);

    # Establish a scale -- the average size in this list should be 1.25em, with the scale
    # moving up and down from there.
    $multiple = 1.25 / (array_sum($tags) / count($tags));

    foreach ($tags as $tag => $count)
    {
        $size = round(($count * $multiple), 1);
        if ($size > 4)
        {
            $size = 4;
        }
        elseif ($size < .75)
        {
            $size = .75;
        }

        $page_body .= '<span style="font-size: ' . $size . 'em;"><a href="/bills/tags/' . urlencode($tag) . '/">' . $tag . '</a></span> ';
    }
    $page_body .= '</div>
		</dd>';
}

if (!empty($legislator['batting']))
{
    $page_body .= '<dt>Bills Passed</dt>
		<dd>' . round(($legislator['batting']['passed'] / $legislator['batting']['total'] * 100), 1) . '%
		in ' . $batting_year . '</dd>';
}

/*if (!empty($legislator['contributions']))
{
    $legislator['contributions'] = (array) $legislator['contributions'];
    $page_body .= '<dt>Campaign Contributions</dt>
        <dd>' . $legislator['contributions']['Reports']->{0}->EndingBalance . ' cash on hand '
        . '(<a href="' . $legislator['contributions']['Reports']->{0}->Url . '">'
        . date('F Y', strtotime($legislator['contributions']['Reports']->{0}->PeriodEnd))
        . ' report)</a></dd>';
}*/

if (!empty($legislator['bio']))
{
    $page_body .= '
		<dt>Bio</dt>
		<dd>' . nl2p($legislator['bio']) . '</dd>';
}

# Close the table and this tab's DIV.
$page_body .= '</dl>
	</div>';

# Start a new DIV for top contributions.
/*if (isset($legislator['contributions']['List']))
{

    # Sort the list by total cumulative contributions, and keep only the top 10.
    function cmp($a, $b)
    {
        if ($a->cumulative_amount == $b->cumulative_amount) return 0;
        return ($a->cumulative_amount > $b->cumulative_amount) ? -1 : 1;
    }
    $contributions = $legislator['contributions']['List'];
    usort($contributions, 'cmp');
    $contributions = array_slice($contributions, 0, 10);
    $page_body .= '
        <div id="donors">
        <table style="width: 100%;">
            <caption>Top 10 Donors</caption>
            <tbody>';

    foreach ($contributions as $contribution)
    {
        $page_body .= '
                <tr>
                    <td>' . $contribution->name_first . ' ' . $contribution->name_middle . ' ' . $contribution->name_last . '</td>
                    <td>' . $contribution->occupation . '</td>
                    <td>' . ( ($contribution->address_state == 'VA') ? $contribution->address_city : $contribution->address_state). '</td>
                    <td>$' . number_format(round($contribution->cumulative_amount)) . '</td>
                </tr>';
    }

    $page_body .= '
            </tbody>
        </table>
        <p>Get <a href="http://openva.com/campaign-finance/contributions/'
            . $legislator['contributions']['CommitteeCode'] . '.csv">a list of all contributions as '
            .'CSV</a> or <a href="http://openva.com/campaign-finance/contributions/'
            . $legislator['contributions']['CommitteeCode'] . '.json">JSON</a>.</p>
        </div>';

}*/

# Start a new DIV for news mentions.
$page_body .= '
<div id="media">
	<table style="width: 100%">
	<caption>Recent Mentions in the Media</caption>
	<tbody>';

# Assemble the Google News URL.
$google_rss = 'https://news.google.com/news/rss/search/section/q/%22+' .
    urlencode($legislator['name']) . '%22+' . (($legislator['chamber'] == 'house') ? 'del+OR+delegate' : 'sen+OR+senator');
$google_link = 'https://news.google.com/news/search/section/q/%22' . urlencode($legislator['name']) .
    '%22+' . (($legislator['chamber'] == 'house') ? 'del+OR+delegate' : 'sen+OR+senator');

/*
 * Instantiate SimplePie
 */
$newsfeed = new SimplePie();
$newsfeed->set_feed_url($google_rss);
$newsfeed->init();
$newsfeed->handle_content_type();

/*
 * Iterate through the returned feed, limited to 5 items.
 */
$rss_count = 0;
foreach ($newsfeed->get_items() as $item)
{

    $tmp = explode(' - ', $item->get_title());
    $title = '';
    for ($i=0; $i<count($tmp); $i++)
    {
        if ($i < (count($tmp) - 1))
        {
            $title .= $tmp[$i];
        }
        else
        {
            $source = $tmp[$i];
        }
    }

    /*
     * Clean up the description into a decent summary.
     */
    $summary = strip_tags($item->get_description());
    $summary = str_replace($tmp, '', $summary);
    # Don't trail off if we already have a period at the end.
    $summary = str_replace('. ...', '.', $summary);
    # Hack off the dateline.
    $summary = preg_replace('/([a-z]{3}) ([0-9]+), 20([0-9]{2})/Di', '', $summary);
    # Remove the indication of how many hours ago this news item was written.
    $summary = preg_replace('/([0-9]*) hour(s*) ago/Di', '', $summary);
    # Hack off the state that often leads off the article.
    $summary = preg_replace('/,&nbsp;([A-Z]{2})&nbsp;- /D', '', $summary);
    
    $page_body .= '
        <tr>
        <td>
        <h3>' . $source . ': <a href="' . $item->get_permalink() . '">' . $title . '</a></h3>' .
        '<p>' . $item->get_date('F j, Y | g:i a') . '<br />' . $summary . '</p>
        </td>
        </tr>';

    $rss_count++;
    if ($rss_count == 5)
    {
        break;
    }

}

# Provide a link to read more.
$page_body .= '
    <tr><td><div style="float: right;">
        <a href="' . $google_link . '">More Media Mentions &gt;&gt;</a>
    </div></td></tr>';

# End the DIV for news mentions.
$page_body .= '</tbody></table></div>';

# News from the legislator's website.
if (!empty($legislator['rss_url']))
{

    $newsfeed->set_feed_url($legislator['rss_url']);
    $newsfeed->init();
    $newsfeed->handle_content_type();

    # Start a new DIV for legislator's blogs, etc.
    $page_body .= '
	<div id="news">
		<table style="width: 100%">
            <tbody>
            <caption>From the Legislator’s Website</caption>';
   
    $rss_count = 0;
    foreach ($newsfeed->get_items() as $item)
    {
        
        $page_body .= '
            <tr><td>
            <h3><a href="' . $item->get_permalink() . '">' . $item->get_title() . '</a></h3>' .
            '<p>';
        $page_body .= $item->get_date('F j, Y') . '<br />';
        $page_body .= strip_tags($item->get_description()) . '</p></td></tr>';
    
    }
    # End the DIV for news mentions.
    $page_body .= '
				</tbody>
			</table>
	</div>';
}

if ($legislator['videos'] !== FALSE)
{
    $video = new Video;
    $video->legislator_id = $legislator['id'];
    $video->by_legislator();

    # Start a new DIV for this legislator's highlights reel.
    /*
     * Add the Flowplayer code.
     */
    $html_head .= '
		<script src="/js/flowplayer-6.0.5/flowplayer.min.js"></script>
		<link rel="stylesheet" href="/js/flowplayer-6.0.5/skin/minimalist.css">';

    $page_body .= '
	<div id="video" style="width: 100%; clear: left;">
		<p>These are all of the video clips of ' . $legislator['name'] . '’s remarks on the floor of the
		' . ucfirst($legislator['chamber']) . ' since ' . mb_substr($video->clips->{0}->date, 0, 4) . '.
		There are ' . count((array) $video->clips) . ' video clips in all.</p>

		<div class="flowplayer" style="display:block; width:450px; height:337px;" id="player"></div>

		<script>
			/* Create the playlist. */
			var allVideos = [';

    foreach ($video->clips as $clip)
    {
        $clip = (array) $clip;
        $page_body .= '
			{
				sources: [{
					type: "video/mp4",
					src: "' . $clip['path'] . '",
					date: "' . $clip['date'] . '",
					start: ' . $clip['start'] . ',
					duration: ' . $clip['duration'] . ',
					cuepoints: [' . ($clip['start'] + $clip['duration']) . ' ]
				}]
			},';
    }

    $page_body .= "];

			flowplayer(function (api, root) {
					api.on('ready', function() {
						firstplayer.seek(api.video.start);
					});
				});

			/* Load the playlist into Flowplayer. */
			flowplayer('#player', {
				playlist: allVideos
			});

			/* When we hit the cuepoint, advance to the next video. */
			var firstplayer = flowplayer('#player');
			firstplayer.on('cuepoint', function(e, api, cuepoint) {
				if (firstplayer.video.is_last == false) {
					firstplayer.next();
				}
				else {
					firstplayer.pause();
				}
			});
		</script>
	</div>";
}

# Close the DIV that contains these tabs.
$page_body .= '</div>';

# List the legislator's bills
if (count($legislator['bills']) > 0)
{
    $page_body .= '<div style="clear: both;" id="bills" class="tabs">
		<h2>Bills</h2>';

    $year = 0;
    $i=0;
    foreach ($legislator['bills'] as $bill)
    {
        $bill = (array) $bill;
        $bills[$bill{'year'}][] = $bill;
    }

    # Start the tab header code
    $page_body .= '
		<ul>';

    # Step through each year and generate a tab.
    foreach ($bills as $year => $bill)
    {
        if (count($bills) > 9)
        {
            $page_body .= '
				<li><a href="#' . $year . '">' . preg_replace('/^20/', "‘", $year) . '</a></li>';
        }
        else
        {
            $page_body .= '
				<li><a href="#' . $year . '">' . $year . '</a></li>';
        }
    }

    # End the tab header code.
    $page_body .= '
		</ul>';

    # Now step through each year, and each bill within each year, and generate the tab's data.
    foreach ($bills as $year => $year_bills)
    {
        $page_body .= '
			<div id="' . $year . '" class="bills">
				<ul>';

        foreach ($year_bills as $bill)
        {
            $page_body .= '
            <li><a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number']) . '/" class="balloon">' . mb_strtoupper($bill['number']) . balloon($bill, 'bill-noleg') . '</a>: ' . $bill['catch_line'] . '</li>';
        }

        $page_body .= '
				</ul>
			</div>';
    }

    # Close the Bills DIV
    $page_body .= '</div>';
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
