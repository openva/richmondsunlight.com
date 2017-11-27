<?php

###
# Committees
# 
# PURPOSE
# Individual committees.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/settings.inc.php');
include_once('includes/functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
$chamber = mysql_escape_string($_REQUEST['chamber']);
$committee = mysql_escape_string($_REQUEST['committee']);

# Select the basic committee information.
$sql = 'SELECT id, shortname, name, chamber, meeting_time, url
		FROM committees
		WHERE shortname="'.$committee.'"
		AND chamber="'.$chamber.'"';
$result = @mysql_query($sql);
if (@mysql_num_rows($result) == 0)
{
	header("Status: 404 Not Found\n\r");
	include('404.php');
	exit();
}

$committee = @mysql_fetch_array($result);

# Select the representatives on this committee.
$sql = 'SELECT representatives.shortname, representatives.name_formatted AS name,
		representatives.name AS name_simple, committee_members.position, representatives.email
		FROM representatives
		LEFT JOIN
		committee_members
			ON representatives.id=committee_members.representative_id
		WHERE committee_members.committee_id='.$committee['id'].'
		AND (committee_members.date_ended > now() OR committee_members.date_ended IS NULL)
		AND (representatives.date_ended >= now() OR representatives.date_ended IS NULL)
		ORDER BY committee_members.position DESC, representatives.name ASC';
$result = @mysql_query($sql);
while ($member = @mysql_fetch_array($result))
{
	$member['name_simple'] = pivot($member['name_simple']);
	$committee['member'][] = $member;
}

# CLEAN UP THE DATA
$committee = array_map_multi('stripslashes', $committee);

# PAGE METADATA
$page_title = ucfirst($chamber).' '.$committee['name'].' Committee';
$site_section = 'committees';

# PAGE SIDEBAR
if (!empty($committee['url']))
{
	$page_sidebar .= '
		<div class="box">
			<h3>About This Committee</h3>
			More information about the '.$committee['name'].' Committee can be found at
			<a href="'.$committee['url'].'">their website</a>.
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
				AND committee_id='.$committee['id'].') AS bill_count
		FROM meetings
		WHERE committee_id='.$committee['id'].' AND date >= now()
		ORDER BY date_raw ASC
		LIMIT 1';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 1)
{
	$tmp = mysql_fetch_array($result);
	$committee['next_meeting'] = $tmp['date'];
	$committee['meeting_year'] = $tmp['year'];
	$committee['meeting_month'] = $tmp['month'];
	$committee['meeting_day'] = $tmp['day'];
	$committee['meeting_bill_count'] = $tmp['bill_count'];
	if (!empty($tmp['time']))
	{
		$committee['next_meeting'] .= ' at '.$tmp['time'];
	}
}

$page_sidebar .= '
		<div class="box">
			<h3>Meeting Schedule</h3>
			<p>The '.$committee['name'].' committee meets when the '.$committee['chamber'].' is '
			.'in session, '.$committee['meeting_time'].'.</p>';
if (isset($committee['next_meeting']))
{
	$page_sidebar .= '<p>The next scheduled meeting is on '.$committee['next_meeting'].'.
		'.number_format($committee['meeting_bill_count']).' bills are on the agenda.
		<a href="/schedule/'.$committee['meeting_year'].'/'.$committee['meeting_month']
		.'/'.$committee['meeting_day'].'/#'.$committee['chamber'].'-'.$committee['shortname']
		.'">Details »</a></p>';
}
$page_sidebar .= '
		</div>';
		
		
# Overall batting average.
$sql = 'SELECT COUNT(*) AS failed,
			(SELECT COUNT(*)
			FROM bills
			WHERE last_committee_id='.$committee['id'].'
			AND session_id='.SESSION_ID.') AS total
		FROM bills
		WHERE status = "failed" AND last_committee_id = '.$committee['id'].'
		AND session_id='.SESSION_ID;

$result = @mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{
	$stats = @mysql_fetch_array($result);
	
	# "We'll have no dividing by zero in this house, young man."
	if (($stats['failed'] > 0) && ($stats['total'] > 0))
	{
		$page_sidebar .= '
	<div class="box">
		<h3>Stats</h3>
		<p>'.(100 - round(($stats['failed'] / $stats['total'] * 100), 0)).'% of the
		'.$stats['total'].' bills considered by the '.$committee['name'].' Committee
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
			WHERE last_committee_id='.$committee['id'].'
			AND session_id='.SESSION_ID.' AND representatives.party = party1)
			AS total
		FROM bills
		LEFT JOIN representatives
		ON bills.chief_patron_id = representatives.id
		WHERE status = "failed" AND last_committee_id = '.$committee['id'].'
		AND session_id='.SESSION_ID.'
		GROUP BY party
		ORDER BY party DESC';
$result = @mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{
	$page_sidebar .= '<p>';
	
	while ($stats = @mysql_fetch_array($result))
	{
		
		if ($stats['party'] == 'R') $stats['party'] = 'Republican';
		elseif ($stats['party'] == 'D') $stats['party'] = 'Democrat';
		elseif ($stats['party'] == 'I') $stats['party'] = 'Independent';
	
		# "We'll have no dividing by zero in this house, young man."
		if (($stats['failed'] > 0) && ($stats['total'] > 0))
		{
			$page_sidebar .= (100 - round(($stats['failed'] / $stats['total'] * 100), 0)).'% of
			the '.$stats['total'].' bills introduced by '.$stats['party'].'s have passed.  ';
		}
	}
		
	$page_sidebar .= '</p>';
}


# Bills in this committee.
$sql = 'SELECT chamber, number, catch_line
		FROM bills
		WHERE session_id='.SESSION_ID.' AND last_committee_id='.$committee['id'].'
		AND status != "failed" AND status != "continued" AND status != "approved"
		AND status != "passed '.$committee['chamber'].'" AND status != "passed"
		AND status != "vetoed" AND status != "passed committee" AND status != "failed committee"
		ORDER BY hotness';
$result = @mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{
	
	$total_bills = @mysql_num_rows($result);
	
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
		<p>There are currently <a href="/bills/committee/'.$committee['chamber'].'/'.$committee['shortname'].'/">'
			.$total_bills.' bills</a> awaiting review by this committee.';
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
	while ($bill = @mysql_fetch_array($result))
	{
		$bill = array_map('stripslashes', $bill);
		$page_sidebar .= '<li><a href="/bill/'.SESSION_YEAR.'/'.$bill['number'].'/" class="bill">'
			.strtoupper($bill['number']).'</a>: '.$bill['catch_line'].'</li>';
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
		WHERE committees.id='.$committee['id'].' AND bills.session_id = '.SESSION_ID.'
		GROUP BY tags.tag
		ORDER BY tags.tag ASC';
$result = @mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{
	$page_sidebar .= '
	<a href="javascript:openpopup(\'/help/tag-clouds/\')"><img src="/images/help-gray.gif" class="help-icon" alt="?" /></a>
	
	<div class="box">
		<h3>Tag Cloud</h3>
		<div class="tags">';
	$top_tag = 1;
	$top_tag_size = 3;
	while ($tag = @mysql_fetch_array($result))
	{
		$tags[] = array_map('stripslashes', $tag);
		if ($tag['count'] > $top_tag) $top_tag = $tag['count'];
	}
	if ($top_tag == 1) $top_tag_size = 1;
	for ($i=0; $i<count($tags); $i++)
	{
		$font_size = round(($tags[$i]['count'] / $top_tag * $top_tag_size), 2);
		if ($font_size < '.75') $font_size = '.75';
		$page_sidebar .= '<span style="font-size: '.$font_size.'em;">
				<a href="/bills/tags/'.urlencode($tags[$i]['tag']).'/">'.$tags[$i]['tag'].'</a>
			</span>';
	}
	$page_sidebar .= '
		</div>
	</div>
	</div>';
}

# PAGE CONTENT

# Member Listing
if (is_array($committee['member']))
{
	$page_body = '
			<h2>Members</h2>
			<ul>';
	foreach ($committee['member'] AS $member)
	{
		$page_body .= '<li><a href="/legislator/'.$member['shortname'].'/" class="legislator">'.$member['name']
			.'</a>';
		if (!empty($member['position']))
		{
			$page_body .= ' <strong>'.ucwords($member['position']).'</strong>';
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
		WHERE parent_id='.$committee['id'].'
		ORDER BY name ASC';
$result = @mysql_query($sql);

# If there are no subcommittees.
if (@mysql_num_rows($result) == 0)
{
	$page_body .= '<p>This committee has no subcommittees.</p>';
}

# If there are subcommittees.
else
{
	$page_body .= '<ul>';
	while ($subcommittee = @mysql_fetch_array($result))
	{
		$subcommittee = array_map('stripslashes', $subcommittee);
		$page_body .= '<li>'.$subcommittee['name'];
		if (!empty($subcommittee['meeting_time']))
		{
			$page_body .= '<br /><small>'.$subcommittee['meeting_time'].'</small>';
		}
		$page_body .= '</li>';
	}
	$page_body .= '</ul>';
}

if (is_array($committee['member']))
{
	# Generate a list of all e-mail addresses for the members of this committee.
	$page_body .= '
			<h2>E-Mail Contact List</h2>
			<p>Copy the below into your e-mail client’s “To” field to e-mail every member
			of this committee.</p>
			<textarea style="width: 100%; height: 12em; font-size: .85em;">';
	foreach ($committee['member'] as $member)
	{
		if (!empty($member['email']))
		{
			$page_body .= '&quot;'.$member['name_simple'].'&quot; &lt;'.$member['email'].'&gt;';
			if (next($committee['member']))
			{
				$page_body .= ', ';
			}
		}
	}
	$page_body .= '</textarea>';
}

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();

?>