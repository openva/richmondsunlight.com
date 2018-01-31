<?php

###
# Legislative Schedule
#
# PURPOSE
# Mashes up various sources of scheduling data to provide an over view of the legislative
# calendar over the coming days.
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
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# LOCALIZE VARIABLES
if (isset($_GET['date']))
{
	$date = $_GET['date'];
	$tmp = explode('-', $date);
	$date_formatted = date('m/d/Y', mktime(0,0,0,$tmp[1],$tmp[2],$tmp[0]));
}
else
{
	# If it's later 4 PM or later, show tomorrow's docket.
	if (date('H') >= 16)
	{
		$date = date('Y-m-d', (time()+(60*60*12)));
		$date_formatted = date('l, F d, Y', (time()+(60*60*12)));
	}
	else
	{
		$date = date('Y-m-d');
		$date_formatted = date('m/d/Y');
	}
}

# PAGE METADATA
$page_title = 'Schedule for '.$date_formatted;
$site_section = 'schedule';

# PAGE CONTENT

# First display the general calendar.
$sql = 'SELECT DISTINCT meetings.time AS time_raw, DATE_FORMAT(meetings.time, "%l:%i %p") AS time,
		meetings.timedesc, description, location
		FROM meetings
		LEFT JOIN committees
			ON meetings.committee_id=committees.id
		WHERE date="'.$date.'"
		ORDER BY timedesc DESC, time_raw ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	$page_body .= '
		<h2 id="calendar">The Day’s Calendar</h2>';
	while ($meeting = mysql_fetch_array($result))
	{
		$meeting = array_map('stripslashes', $meeting);
		$page_body .= '<p><strong>';
		if (!empty($meeting['timedesc']))
		{
			$page_body .= $meeting['timedesc'];
		}
		else
		{
			$page_body .= $meeting['time'];
		}
		$page_body .= '</strong> '.$meeting['location'].' '.$meeting['description'].'</p>';
	}
}

$page_body .= '<h2>Legislation Scheduled for Hearings</h2>';

# Select the upcoming meetings.
$sql = 'SELECT dockets.date, committees.id AS committee_id, committees.chamber,
		committees2.name as parent_committee, committees.name AS committee,
		committees.parent_id, COUNT(*) AS bills, committees.shortname, meetings.description,
		DATE_FORMAT(meetings.time, "%l:%i %p") AS time, meetings.timedesc, meetings.location
		FROM dockets
		LEFT JOIN committees
			ON dockets.committee_id=committees.id
		LEFT JOIN committees AS committees2
			ON committees.parent_id=committees2.id
		LEFT JOIN meetings
			ON committees.id = meetings.committee_id
		WHERE dockets.date = "'.mysql_real_escape_string($date).'"
		GROUP BY dockets.committee_id
		ORDER BY committees.chamber DESC, committees.name ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) < 1)
{
	$page_body .= '<p>No committee or subcommittee meetings are currently scheduled for today.</p>';
}
else
{

	# Step through each scheduled meeting.
	while ($meeting = mysql_fetch_array($result))
	{

		# Clean up the data.
		$meeting = array_map('stripslashes', $meeting);

		# Select a listing of the bills being heard in this committee on this date.
		// We use SELECT DISTINCT to work around a bug that exists as of this writing (January
		// 2008) in the docket retrieval code that's inserting many duplicates of each bill.
		$sql = 'SELECT DISTINCT bills.number, bills.chamber, bills.catch_line,
				bills.status AS status_raw, representatives.name AS patron, sessions.year,
				bills.date_introduced, bills.status
				FROM bills
				LEFT JOIN dockets
					ON bills.id=dockets.bill_id
				LEFT JOIN representatives
					ON bills.chief_patron_id = representatives.id
				LEFT JOIN sessions
					ON bills.session_id = sessions.id
				WHERE dockets.date = "'.$meeting['date'].'"
				AND committee_id='.$meeting['committee_id'].'
				ORDER BY bills.chamber DESC,
				SUBSTRING(bills.number FROM 1 FOR 2) ASC,
				CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
		$result2 = mysql_query($sql);
		if (mysql_num_rows($result2) > 0)
		{
			# Initialize the array.
			$bills = array();

			while ($bill = mysql_fetch_array($result2))
			{
				# Save the bills into an array to use later.
				$bill = array_map('stripslashes', $bill);
				$bills[] = $bill;
			}
		}

		# If no bills are found, we want to unset the variable, if it was used last time, to
		# avoid listing bills from a prior-listed committee in this one.
		else
		{
			if (isset($bills))
			{
				unset($bills);
			}
		}

		# If this a subcommittee, rather than a committee, shuffle around the names of the array
		# elements.
		if (!empty($meeting['parent_committee']))
		{
			$meeting['subcommittee'] = $meeting['committee'];
			$meeting['committee'] = $meeting['parent_committee'];
		}

		$page_body .= '<table id="'.$meeting['chamber'].'-'.$meeting['shortname'].'" '
				.'class="bill-listing sortable">
				<caption>';
		# Display the committee name, and optionally the subcommittee name.
		$page_body .= ucfirst($meeting['chamber']).' '.$meeting['committee'];
		if (!empty($meeting['subcommittee']))
		{
			$page_body .= ' Committee, '.$meeting['subcommittee'].' Subcommittee';
		}
		if (!empty($meeting['time']))
		{
			$page_body .= ' ('.$meeting['time'].')';
		}
		elseif (!empty($meeting['timedesc']))
		{
			$page_body .= ' ('.$meeting['timedesc'].')';
		}
		$page_body .= '</caption>
			<thead>
				<tr>
					<th>#</th>
					<th>Title</th>
				</tr>
			</thead>
			<tbody>';

		# If we know the bills that will be in this committee, list them.
		if (isset($bills))
		{
			$page_body .= '<p>';
			foreach ($bills as $bill)
			{
				$page_body .= '
						<tr>
							<td><a href="/bill/'.$bill['year'].'/'.strtolower($bill['number']).'/" class="balloon">'.strtoupper($bill['number']).balloon($bill, 'bill').'</a></td>
							<td>'.$bill['catch_line'].'</td>
						</tr>';
			}
			$page_body .= '</p>';
		}
		$page_body .= '
			</tbody>
		</table>';
	}
}

# PAGE SIDEBAR
$sql = 'SELECT DISTINCT DATE_FORMAT(date, "%m/%d/%Y") AS date_formatted, date
		FROM meetings
		WHERE date >= now()
		ORDER BY date ASC
		LIMIT 10';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	$page_sidebar = '
	<div class="box">
		<h3>Upcoming Dates</h3>
		<ul>';
	while ($upcoming = mysql_fetch_array($result))
	{
		# Create the URL for this day's link.
		$upcoming['url'] = '/schedule/'.str_replace('-', '/', $upcoming['date']).'/';
		$page_sidebar .= '
			<li><a href="'.$upcoming['url'].'">'.$upcoming['date_formatted'].'</a></li>';
	}
	$page_sidebar .= '
		</ul>
	</div>';
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
