<?php

###
# List Votes for a Specific Legislator
#
# PURPOSE
# Accepts the shortname of a given legislator and a year, and provides a table of that
# legislator's voting record in that period.
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
$database = new Database;
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
if (isset($_GET['shortname']))
{
    $shortname = $_GET['shortname'];
}
if (isset($_GET['year']))
{
    $year = $_GET['year'];
}
if (isset($_GET['page']))
{
    $page = $_GET['page'];
}

# PAGE METADATA
$page_title = 'Vote';
$site_section = '';

# PAGE CONTENT

# Create a new legislator object.
$leg = new Legislator();
# Get the ID for this shortname.
$leg_id = $leg->getid($shortname);
if ($leg_id === false)
{
    header("Status: 404 Not Found\n\r") ;
    include '404.php';
    exit();
}
# Return the legislator's data as an array.
$legislator = $leg->info($leg_id);

# Establish a more descriptive page title.
$page_title = $legislator['prefix'] . ' ' . $legislator['name'] . '’s ' . $year . ' Voting Record';

# Select the vote data from the database.
$sql = 'SELECT bills.number AS bill_number, bills.catch_line, representatives_votes.vote,
		votes.outcome, committees.name AS committee, committees.shortname AS committee_shortname,
		bills_status.date, votes.lis_id
		FROM bills
		LEFT JOIN bills_status ON bills.id = bills_status.bill_id
		LEFT JOIN votes ON bills_status.lis_vote_id = votes.lis_id
		LEFT JOIN representatives_votes ON votes.id = representatives_votes.vote_id
		LEFT JOIN committees ON votes.committee_id = committees.id
		LEFT JOIN representatives ON representatives_votes.representative_id=representatives.id
		LEFT JOIN sessions ON bills.session_id = sessions.id
		WHERE representatives.shortname = "' . mysqli_real_escape_string($GLOBALS['db'], $shortname) . '"
		AND sessions.year = ' . mysqli_real_escape_string($GLOBALS['db'], $year) . '
		AND bills_status.date IS NOT NULL AND votes.session_id=sessions.id
		AND bills_status.status NOT LIKE "Constitutional reading%"
		ORDER BY date ASC, committee ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body = '
		<p><a href="/legislator/' . $shortname . '/votes/' . $year . '.csv">Download List as a
			Spreadsheet</a> <code>(' . $shortname . '-' . $year . '.csv)</code></p>

		<p><em>These are not as simple as you might think.</em> Somebody voting “yes” on a bill
		might be voting “yes” that it should be killed. Somebody voting “no” on a bill might
		be voting that, no, it should not be sent to a backwater committee to ensure that it
		never sees the light of day. Click on the “Outcome” link to find out exactly what was
		being voted on.</p>

		<h2>Key</h2>
		<ul>
			<li><code>Y</code> = “yes”</li>
			<li><code>N</code> = “no”</li>
			<li><code>X</code> = “did not vote”</li>
			<li><code>A</code> = “abstained from voting”</li>
		</ul>
		<table>
			<thead>
				<tr>
					<th>Bill #</th>
					<th>Title</th>
					<th>Vote</th>
					<th>Outcome</th>
					<th>Committee</th>
					<th>Date</th>
				</tr>
			</thead>
			<tbody>';
    while ($vote = mysqli_fetch_array($result))
    {
        $vote = array_map('stripslashes', $vote);
        $page_body .= '
			<tr>
				<td><a href="/bill/' . $year . '/' . $vote['bill_number'] . '/">'
                    . mb_strtoupper($vote['bill_number']) . '</a></td>
				<td>' . $vote['catch_line'] . '</td>
				<td>' . $vote['vote'] . '</td>
				<td><a href="/bill/' . $year . '/' . $vote['bill_number'] . '/'
                    . mb_strtolower($vote['lis_id']) . '/">' . $vote['outcome'] . '</td>
				<td>';
        if (empty($vote['committee']))
        {
            $page_body .= ucfirst($legislator['chamber']) . ' Floor';
        }
        else
        {
            $page_body .= '<a href="/committee/' . $legislator['chamber'] . '/'
                . $vote['committee_shortname'] . '/">' . $vote['committee'] . '</a>';
        }
        $page_body .= '</td>
				<td>' . $vote['date'] . '</td>
			</tr>';
    }
    $page_body .= '
			</tbody>
		</table>';
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
