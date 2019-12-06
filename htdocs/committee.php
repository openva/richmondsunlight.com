<?php

###
# Committees
#
# PURPOSE
# Individual committees.
#
###

include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
$chamber = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['chamber']);
$shortname = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['committee']);

/*
 * Get basic data about this committee.
 */
$committee = new Committee;
$committee->chamber = $chamber;
$committee->shortname = $shortname;

if (!$committee->info() || !$committee->members())
{
    header('Status: 404 Not Found');
    include '404.php';
    exit();
}

# PAGE METADATA
$page_title = ucfirst($chamber) . ' ' . $committee->name . ' Committee';
$site_section = 'committees';

# PAGE SIDEBAR
if (!empty($committee->url))
{
    $page_sidebar .= '
		<div class="box">
			<h3>About This Committee</h3>
			More information about the ' . $committee->name . ' Committee can be found at
			<a href="' . $committee->url . '">their website</a>.
		</div>';
}


# Data about the next scheduled meeting.
$sql = 'SELECT date AS date_raw, DATE_FORMAT(date, "%W, %m/%d/%Y") AS date,
		DATE_FORMAT(date, "%Y") AS year, DATE_FORMAT(date, "%m") AS month,
		DATE_FORMAT(date, "%d") AS day, DATE_FORMAT(time, "%l:%i %p") AS time, timedesc,
			(SELECT COUNT(DISTINCT bills.number)
				FROM bills
				LEFT JOIN dockets
					ON bills.id=dockets.bill_id
				LEFT JOIN sessions
					ON bills.session_id = sessions.id
				WHERE dockets.date = meetings.date
				AND committee_id=' . $committee->id . ') AS bill_count
		FROM meetings
		WHERE committee_id=' . $committee->id . ' AND date >= now()
		ORDER BY date_raw ASC
		LIMIT 1';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) == 1)
{
    $tmp = mysqli_fetch_array($result);
    $committee->meeting = new stdClass;
    $committee->meeting->next = $tmp['date'];
    $committee->meeting->year = $tmp['year'];
    $committee->meeting->month = $tmp['month'];
    $committee->meeting->day = $tmp['day'];
    $committee->meeting->bill_count = $tmp['bill_count'];
    if (!empty($tmp['time']))
    {
        $committee->meeting->next .= ' at ' . $tmp['time'];
    }
    $committee->meeting->time = $committee->meeting_time;
}

$page_sidebar = '
		<div class="box">
			<h3>Meeting Schedule</h3>
			<p>The ' . $committee->name . ' committee meets when the ' . $committee->chamber . ' is '
            . 'in session, ' . $committee->meeting->time . '.</p>';
if (isset($committee->meeting->next))
{
    $page_sidebar .= '<p>The next scheduled meeting is on ' . $committee->meeting->next . '. '
        . number_format($committee->meeting->bill_count) . ' bills are on the agenda.
		<a href="/schedule/' . $committee->meeting->year . '/' . $committee->meeting->month
        . '/' . $committee->meeting->day . '/#' . $committee->chamber . '-' . $committee->shortname
        . '">Details »</a></p>';
}
$page_sidebar .= '</div>';


# Overall batting average.
$sql = 'SELECT COUNT(*) AS failed,
			(SELECT COUNT(*)
			FROM bills
			WHERE last_committee_id=' . $committee->id . '
			AND session_id=' . SESSION_ID . ') AS total
		FROM bills
		WHERE status = "failed" AND last_committee_id = ' . $committee->id . '
		AND session_id=' . SESSION_ID;

$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $stats = mysqli_fetch_array($result);

    # "We'll have no dividing by zero in this house, young man."
    if (($stats['failed'] > 0) && ($stats['total'] > 0))
    {
        $page_sidebar .= '
	<div class="box">
		<h3>Stats</h3>
		<p>' . (100 - round(($stats['failed'] / $stats['total'] * 100), 0)) . '% of the
		' . $stats['total'] . ' bills considered by the ' . $committee->name . ' Committee
		this year have passed.</p>';
    }
}


# Partisan batting average.
$sql = 'SELECT representatives.party, representatives.party AS party1,
		COUNT(*) AS failed,
			(SELECT COUNT(*)
			FROM bills
			LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
			WHERE last_committee_id=' . $committee->id . '
			AND session_id=' . SESSION_ID . ' AND representatives.party = party1)
			AS total
		FROM bills
		LEFT JOIN representatives
		    ON bills.chief_patron_id = representatives.id
		WHERE status = "failed"
            AND last_committee_id = ' . $committee->id . '
            AND session_id=' . SESSION_ID . '
        GROUP BY party
            ORDER BY party DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_sidebar .= '<p>';

    while ($stats = mysqli_fetch_array($result))
    {
        if ($stats['party'] == 'R')
        {
            $stats['party'] = 'Republican';
        }
        elseif ($stats['party'] == 'D')
        {
            $stats['party'] = 'Democrat';
        }
        elseif ($stats['party'] == 'I')
        {
            $stats['party'] = 'Independent';
        }

        # "We'll have no dividing by zero in this house, young man."
        if (($stats['failed'] > 0) && ($stats['total'] > 0))
        {
            $page_sidebar .= (100 - round(($stats['failed'] / $stats['total'] * 100), 0)) . '% of
			the ' . $stats['total'] . ' bills introduced by ' . $stats['party'] . 's have passed.  ';
        }
    }

    $page_sidebar .= '</p>';
}


# Bills in this committee.
$sql = 'SELECT chamber, number, catch_line
		FROM bills
		WHERE session_id=' . SESSION_ID . ' AND last_committee_id=' . $committee->id . '
		AND status != "failed" AND status != "continued" AND status != "approved"
		AND status != "passed ' . $committee->chamber . '" AND status != "passed"
		AND status != "vetoed" AND status != "passed committee" AND status != "failed committee"
		ORDER BY hotness';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $total_bills = mysqli_num_rows($result);

    # List only the last five.
    if ($total_bills < 5)
    {
        $listed_bills = $total_bills - 1;
    }
    else
    {
        $listed_bills = 4;
    }

    $page_sidebar .= '
	<div class="box">
		<h3>Bills in this Committee</h3>
		<p>There are currently <a href="/bills/committee/' . $committee->chamber . '/' . $committee->shortname . '/">'
            . $total_bills . ' bills</a> awaiting review by this committee.';
    if ($total_bills > ($listed_bills + 1))
    {
        $page_sidebar .= ' Of those bills, here are the five that have generated the most
			interest:';
    }
    else
    {
        $page_sidebar .= ' Those are:';
    }
    $page_sidebar .= '</p>
		<ul>';
    $i=0;
    while ($bill = mysqli_fetch_array($result))
    {
        $bill = array_map('stripslashes', $bill);
        $page_sidebar .= '<li><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/" class="bill">'
            . mb_strtoupper($bill['number']) . '</a>: ' . $bill['catch_line'] . '</li>';
        if ($i >= $listed_bills)
        {
            break;
        }
        $i++;
    }
    $page_sidebar .= '
		</ul>
	</div>';
}

# Tag Cloud
$sql = 'SELECT COUNT(*) AS count, tags.tag
		FROM tags
		LEFT JOIN bills
			ON tags.bill_id = bills.id
		LEFT JOIN committees
			ON bills.last_committee_id=committees.id
		AND bills.current_chamber=committees.chamber
		WHERE committees.id=' . $committee->id . ' AND bills.session_id = ' . SESSION_ID . '
		GROUP BY tags.tag
		ORDER BY tags.tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_sidebar .= '
	<div class="box">
		<h3>Tag Cloud</h3>
		<div class="tags">';
    $top_tag = 1;
    $top_tag_size = 3;
    while ($tag = mysqli_fetch_array($result))
    {
        $tags[] = array_map('stripslashes', $tag);
        if ($tag['count'] > $top_tag)
        {
            $top_tag = $tag['count'];
        }
    }
    if ($top_tag == 1)
    {
        $top_tag_size = 1;
    }
    for ($i=0; $i<count($tags); $i++)
    {
        $font_size = round(($tags[$i]['count'] / $top_tag * $top_tag_size), 2);
        if ($font_size < '.75')
        {
            $font_size = '.75';
        }
        $page_sidebar .= '<span style="font-size: ' . $font_size . 'em;">
				<a href="/bills/tags/' . urlencode($tags[$i]['tag']) . '/">' . $tags[$i]['tag'] . '</a>
			</span>';
    }
    $page_sidebar .= '
		</div>
	</div>
	</div>';
}

# PAGE CONTENT

# Member Listing
if (is_array($committee->members))
{
    $page_body = '
			<h2>Members</h2>
			<ul>';
    foreach ($committee->members as $member)
    {
        $page_body .= '<li><a href="/legislator/' . $member['shortname'] . '/" class="legislator">' . $member['name']
            . '</a>';
        if (!empty($member['position']))
        {
            $page_body .= ' <strong>' . ucwords($member['position']) . '</strong>';
        }
    }
    $page_body .= '
			</ul>';
}


# Subcommittees
$page_body .= '
		<h2>Subcommittees</h2>';

# Perform the database query.
$sql = 'SELECT name, meeting_time
		FROM committees
		WHERE parent_id=' . $committee->id . '
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);

# If there are no subcommittees.
if (mysqli_num_rows($result) == 0)
{
    $page_body .= '<p>This committee has no subcommittees.</p>';
}

# If there are subcommittees.
else
{
    $page_body .= '<ul>';
    while ($subcommittee = mysqli_fetch_array($result))
    {
        $subcommittee = array_map('stripslashes', $subcommittee);
        $page_body .= '<li>' . $subcommittee['name'];
        if (!empty($subcommittee['meeting_time']))
        {
            $page_body .= '<br /><small>' . $subcommittee['meeting_time'] . '</small>';
        }
        $page_body .= '</li>';
    }
    $page_body .= '</ul>';
}

if (is_array($committee->members))
{
    # Generate a list of all e-mail addresses for the members of this committee.
    $page_body .= '
			<h2>Email Contact List</h2>
			<p>Copy the below into your e-mail client’s “To” field to e-mail every member
			of this committee.</p>
			<textarea style="width: 100%; height: 12em; font-size: .85em;">';
    $num_members = count($committee->members);
    $i=0;
    foreach ($committee->members as $member)
    {
        if (!empty($member['email']))
        {
            $page_body .= '&quot;' . $member['name_simple'] . '&quot; &lt;' . $member['email'] . '&gt;';
            if ($i+1 < $num_members)
            {
                $page_body .= ', ';
            }
        }
        $i++;
    }
    $page_body .= '</textarea>';
}

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
