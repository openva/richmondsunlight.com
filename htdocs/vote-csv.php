<?php

###
# Create Vote CSV
#
# PURPOSE
# Accepts the shortname of a given legislator and a year, and spits out a CSV file
# of that legislator's voting record in that period.
#
# NOTES
# This is not intended to be viewed. It just spits out a CSV file and that's that.
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
$database->connect_old();

# LOCALIZE VARIABLES
$shortname = mysqli_escape_string($db, $_GET['shortname']);
$year = mysqli_escape_string($db, $_GET['year']);

# Select the vote data from the database.
$sql = 'SELECT bills.number AS bill_number, bills.catch_line, representatives_votes.vote,
		votes.outcome, committees.name AS committee, bills_status.date
		FROM bills
		LEFT JOIN bills_status ON bills.id = bills_status.bill_id
		LEFT JOIN votes ON bills_status.lis_vote_id = votes.lis_id
		LEFT JOIN representatives_votes ON votes.id = representatives_votes.vote_id
		LEFT JOIN committees ON votes.committee_id = committees.id
		LEFT JOIN representatives ON representatives_votes.representative_id=representatives.id
		LEFT JOIN sessions ON bills.session_id = sessions.id
		WHERE representatives.shortname = "' . $shortname . '"
		AND sessions.year = ' . $year . ' AND bills_status.date IS NOT NULL
		AND votes.session_id=sessions.id
		ORDER BY date ASC, committee ASC';
$result = mysqli_query($db, $sql);
if (mysqli_num_rows($result) > 0)
{

    # Send the headers to have the data downloaded as a CSV file.
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $shortname . '-' . $year . '.csv');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    echo 'Please note that votes are not necessarily for or against a bill. Many are' . "\n" .
        'procedural votes. Verify the context of votes at richmondsunlight.com' . "\n" .
        'when in doubt.' . "\n\n" . 'Bill #, Title, Vote, Outcome, Committee, Date' . "\n";
    while ($vote = mysqli_fetch_array($result))
    {
        $vote = array_map('stripslashes', $vote);
        $vote['catch_line'] = str_replace('"', '""', $vote['catch_line']);
        echo mb_strtoupper($vote['bill_number']) . ',"' . $vote['catch_line'] . '",' . $vote['vote'] . ',' .
        $vote['outcome'] . ',"' . $vote['committee'] . '",' . $vote['date'] . "\n";
    }
}
