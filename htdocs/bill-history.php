<?php

###
# Bills' History
#
# PURPOSE
# List the history of actions of individual bills.
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
$year = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['year']);
$bill = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['bill']);

# RETRIEVE THE BILL INFO FROM THE DATABASE
$sql = 'SELECT bills.id, bills.number, bills.session_id, bills.chamber,
		bills.catch_line, bills.chief_patron_id, bills.summary,
		bills.full_text, bills.notes, representatives.name AS patron,
		districts.number AS patron_district, sessions.year,
		representatives.party AS patron_party,
		representatives.chamber AS patron_chamber,
		representatives.shortname AS patron_shortname
		FROM bills
		LEFT JOIN sessions
		ON sessions.id=bills.session_id
		LEFT JOIN representatives
		ON representatives.id=bills.chief_patron_id
		LEFT JOIN districts
		ON representatives.district_id=districts.id
		WHERE bills.number="' . $bill . '" AND sessions.year=' . $year;
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $bill = mysqli_fetch_array($result);
    $bill = array_map('stripslashes', $bill);
    $bill['word_count'] = str_word_count($bill['full_text']);
    $bill['patron_suffix'] = '(' . $bill['patron_party'] . '-' . $bill['patron_district'] . ')';
    if ($bill['patron_chamber'] == 'house')
    {
        $bill['patron_prefix'] = 'Rep.';
    }
    elseif ($bill['patron_chamber'] == 'senate')
    {
        $bill['patron_prefix'] = 'Sen.';
    }
}

# PAGE METADATA
$page_title = $bill['number'] . ': ' . $bill['catch_line'];
$site_section = 'bills';

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Additional Data</h3>
		<ul>
			<li><a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number']) . '/">Main Page for ' . $bill['number'] . '</a></li>
			<li><a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number']) . '/fulltext/">Full Text of ' . $bill['number'] . '</a></li>
		</ul>
	</div>';

# PAGE CONTENT
$page_body = '<h2>Status History</h2>';
$sql = 'SELECT DATE_FORMAT(date, "%m/%d/%Y") AS date, date AS date_raw, status
		FROM bills_status
		WHERE bill_id=' . $bill['id'] . ' AND session_id=' . $bill['session_id'] . '
		ORDER BY date_raw ASC, id ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
$page_body .= '<ul>';
while ($history = mysqli_fetch_array($result))
{
    $page_body .= '<li>' . $history['date'] . ' ' . $history['status'] . '</li>';
}
$page_body .= '</ul>';

# OUTPUT THE PAGE
/*display_page('page_title='.$page_title.'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
    '&site_section='.urlencode($site_section));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
