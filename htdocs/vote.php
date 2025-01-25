<?php

###
# List Votes
#
# PURPOSE
# List how legislators voted on this particular vote.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
if (isset($_GET['lis_id'])) {
    $lis_id = mb_strtoupper($_GET['lis_id']);
} else {
    die();
}
if (isset($_GET['year']) && (strlen($_GET['year']) == 4) && is_numeric($_GET['year'])) {
    $year = $_GET['year'];
}
if (isset($_GET['bill']) && strlen($_GET['bill'] <= 7)) {
    $bill = $_GET['bill'];
}

$html_head = '';

# PAGE METADATA
$page_title = 'Vote';
$site_section = '';

# PAGE CONTENT

# Select information about this bill.
$sql = 'SELECT bills.id, bills.number, bills.session_id, bills.chamber, bills.catch_line,
		bills.chief_patron_id, bills.summary, bills.notes, representatives.name AS patron,
		districts.number AS patron_district, sessions.year, bills_status.status AS status_line,
		bills_status.translation AS status_line_translation,
		DATE_FORMAT("%m/%d/%Y", bills_status.date) AS status_line_date,
		representatives.party AS patron_party, representatives.chamber AS patron_chamber,
		representatives.shortname AS patron_shortname, committees.name AS committee_name,
		committees.shortname AS committee_shortname
		FROM bills
		LEFT JOIN votes
			ON bills.id=votes.bill_id
		LEFT JOIN sessions
			ON sessions.id=bills.session_id
		LEFT JOIN representatives
			ON representatives.id=bills.chief_patron_id
		LEFT JOIN districts
			ON representatives.district_id=districts.id
		LEFT JOIN bills_status
			ON bills_status.lis_vote_id=votes.lis_id
		LEFT JOIN committees
			ON votes.committee_id=committees.id
        WHERE bills.number="' . $bill . '" AND sessions.year=' . $year;
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) == 0) {
    http_response_code(404);
    include '404.php';
    exit();
}

$bill = mysqli_fetch_assoc($result);
$bill = array_map('stripslashes', $bill);
$bill = array_map('trim', $bill);

/*
 * Create a new instance of the Vote class, and get aggregate data about the outcome.
 */
$vote_info = new Vote();
$vote_info->lis_id = $lis_id;
$vote_info->session_id = $bill['session_id'];
$vote = $vote_info->get_aggregate();

$page_sidebar = <<<EOD

	<div class="box">
		<h3>Explanation</h3>
		<p>At left is the tally of who voted how on this bill.</p>

		<p>It’s important to understand that most bills are voted on multiple times, and the
		vote is not necessarily simply whether or not the bill should pass.  Be sure to look at
		the bill’s history to determine what, exactly, was being voted on, and at what point in
		the bill’s progress.</p>
	</div>
EOD;


# The status table.
$sql = 'SELECT DISTINCT bills_status.status, bills_status.translation,
		DATE_FORMAT(bills_status.date, "%m/%d/%Y") AS date, bills_status.date AS date_raw,
		bills_status.lis_vote_id, votes.total AS vote_count
		FROM bills_status
		LEFT JOIN votes
			ON bills_status.lis_vote_id = votes.lis_id
		WHERE bills_status.bill_id = ' . $bill['id'] . '
        AND (votes.session_id=bills_status.session_id OR votes.session_id IS NULL)
		ORDER BY date_raw DESC, bills_status.id DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $bill['status_history'] = '';
    while ($status = mysqli_fetch_array($result)) {
        # Provide a link to view this vote, but only if it's not the vote that we're currently
        # viewing.
        if (!empty($status['lis_vote_id']) && ($status['vote_count'] > 0) && ($status['lis_vote_id'] != $lis_id)) {
            $tmp = '<a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number']) . '/' . mb_strtolower($status['lis_vote_id']) . '/">' . $status['status'] . '</a>';
            $status['status'] = $tmp;
        }

        # If we've found this vote in the status history, save it for use in the page text.
        elseif ($status['lis_vote_id'] == $lis_id) {
            $vote_description = trim(preg_replace('/\((.+)\)/', '', $status['status']));
        }

        $bill['status_history'] = '<li' . ($status['lis_vote_id'] == $lis_id ? ' class="highlight"' : '') . '>' . $status['date'] . ' ' . $status['status'] . '</li>' . $bill['status_history'];
    }
    $page_sidebar .= '

		<div class="box">
			<h3>Progress History</h3>
			' . $bill['status_history'] . '
		</div>';
}


$page_title = mb_strtoupper($bill['number']) . ': ' . $bill['catch_line'];
$page_body = '<p>';
if (!empty($bill['committee_name'])) {
    $page_body .= 'This vote on <a href="/bill/' . $year . '/' . $bill['number'] . '/">' . mb_strtoupper($bill['number']) . '</a>
	was held in the <a href="/committee/' . $vote['chamber'] . '/' . $bill['committee_shortname'] . '/">' . ucfirst($vote['chamber']) . '
	' . $bill['committee_name'] . '</a> committee.  ';
} else {
    $page_body .= 'This vote on <a href="/bill/' . $year . '/' . $bill['number'] . '/">'
        . mb_strtoupper($bill['number']) . '</a> was held in the ' . ucfirst($vote['chamber']) . '.  ';
}

if (isset($vote_description)) {
    $page_body .= 'The vote was on this subject: “' . $vote_description . '”. ';
}

$page_body .= 'This vote ' . $vote['outcome'] . 'ed ' . $vote['tally'] . '.</p>';

/*
 * Get detailed information about the vote -- who voted how.
 */
$legislators = $vote_info->get_detailed();

# Step through the legislators data to establish which party voted which way, building up
# an array of data.
foreach ($legislators as $legislator) {
    $legislator['vote'] = mb_strtolower($legislator['vote']);
    $legislator['party'] = mb_strtolower($legislator['party']);
    $graph[$legislator['vote']][$legislator['party']]++;
    $parties[$legislator['party']] = 1;
}

# Make sure that we don't have any missing data, party-wise. That is, Google gets sad if
# we list Democrats, Republicans, and Independents for a "yes" vote, but only Democrats
# and Republicans for a "no" vote. So if any Independents voted anywhere, we need to make
# sure that they're listed everywhere, with a "0".
foreach ($parties as $party => $blargh) {
    foreach ($graph as &$vote) {
        if (!isset($vote[$party])) {
            $vote[$party] = 0;
        }
    }
}

# Sort our parties in the same order. Otherwise they won't match up.
if (isset($graph['y'])) {
    ksort($graph['y']);
}
if (isset($graph['n'])) {
    ksort($graph['n']);
}
if (isset($graph['x'])) {
    ksort($graph['x']);
}
if (isset($graph['a'])) {
    ksort($graph['a']);
}

# Again, sort our parties in the same order.
ksort($parties);

# Only bother displaying a graph if this vote wasn't unanimous. (Most votes are unanimous,
# so this is a real time-saver.)
if (count($graph) > 1) {
    $html_head .= '
    <script src="https://www.gstatic.com/charts/loader.js"></script>
	<script>
		google.load("visualization", "1", {packages:["corechart"]});
		google.setOnLoadCallback(drawChart);
		function drawChart() {
			var data = new google.visualization.DataTable();
			data.addColumn("string", "Vote");';
    foreach ($parties as $party => $blargh) {
        if ($party == 'r') {
            $party = 'Rep.';
        } elseif ($party == 'd') {
            $party = 'Dem.';
        } elseif ($party == 'i') {
            $party = 'Ind.';
        }
        $html_head .= '
			data.addColumn("number", "' . $party . '");';
    }
    $html_head .= '
			data.addRows(' . count($graph) . ');';
    $i = 0;

    foreach ($graph as $outcome => $tally) {
        if ($outcome == 'y') {
            $outcome = 'Voted Yes';
        } elseif ($outcome == 'n') {
            $outcome = 'Voted No';
        } elseif ($outcome == 'x') {
            $outcome = 'Didn\'t Vote';
        } elseif ($outcome == 'a') {
            $outcome = 'Abstained';
        }

        $html_head .= '
				data.setValue(' . $i . ', 0, "' . $outcome . '");';
        $j = 1;

        foreach ($tally as $party => $count) {
            $html_head .= '
				data.setValue(' . $i . ', ' . $j . ', ' . $count . ');';
            $j++;
        }
        $i++;
    }
    $html_head .= '
			var chart = new google.visualization.ColumnChart(document.getElementById("chart"));
			chart.draw(data, {isStacked: true, width: 400, height: 240,';

    # Specify the three colors that will color our graph, that correlate (alphabetically)
    # to Democrats, independents, and Republicans.
    if (count($parties) == 3) {
        $html_head .= '
			colors:["blue", "green", "red"]});';
    }
    # Unless no independents voted, in which case we just want to define colors for Democrats
    # and Republicans.
    else {
        $html_head .= '
			colors:["blue", "red"]});';
    }
    $html_head .= '
		}
	</script>';
    $page_body .= '<div id="chart"></div>';
}

# Display the actual vote results.
$page_body .= '<div>';
foreach ($legislators as $legislator) {
    if (!isset($vote)) {
        $vote = $legislator['vote'];
        if ($vote == 'Y') {
            $display_vote = 'Yes';
        } elseif ($vote == 'N') {
            $display_vote = 'No';
        } elseif ($vote == 'X') {
            $display_vote = 'Didn’t Vote';
        } elseif ($vote == 'A') {
            $display_vote = 'Abstain';
        }
        $page_body .= '<h2>' . $display_vote . '</h2>
		<ul>';
    } elseif ($vote != $legislator['vote']) {
        $vote = $legislator['vote'];
        if ($vote == 'Y') {
            $display_vote = 'Yes';
        } elseif ($vote == 'N') {
            $display_vote = 'No';
        } elseif ($vote == 'X') {
            $display_vote = 'Didn’t Vote';
        } elseif ($vote == 'A') {
            $display_vote = 'Abstain';
        }
        $page_body .= '
		</ul>
			<h2>' . $display_vote . '</h2>
		<ul>';
    }
    $legislator = array_map('stripslashes', $legislator);
    $legislator['patron'] = $legislator['name'];
    $legislator['patron_suffix'] = '(' . $legislator['party'] . '-' . $legislator['district'] . ')';
    $legislator['patron_chamber'] = $legislator['chamber'];
    $legislator['patron_started'] = $legislator['started'];
    $legislator['patron_address'] = $legislator['address'];
    $legislator['patron_shortname'] = $legislator['shortname'];

    $page_body .= '
			<li><a href="/legislator/' . $legislator['shortname'] . '/" class="balloon">' . pivot($legislator['name']) .
             balloon($legislator, 'legislator') . ' ' . $legislator['patron_suffix'] . '</a></li>';
}
$page_body .= '
		</ul>
	</div>';

# OUTPUT THE PAGE

$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
