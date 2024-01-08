<?php

###
# Bill Introduction Activity
#
# PURPOSE
# Lists the bills introduced in the past X days.
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

# Grab the user data.
if (logged_in() === TRUE)
{
    $user = get_user();
}

# LOCALIZE VARIABLES
$days = mysqli_real_escape_string($GLOBALS['db'], $_REQUEST['days']);
if (empty($days))
{
    $days = 7;
}
elseif (!is_numeric($days))
{
    $days = 7;
}

# PAGE METADATA
$page_title = 'Bills Introduced in Past ' . $days . ' Days';
$site_section = 'bills';

# PAGE CONTENT


# Select the most recently introduced bills.
$sql = 'SELECT bills.number, sessions.year, representatives.name AS patron,
		DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced, bills.catch_line,
		(
			SELECT status
			FROM bills_status
			WHERE bill_id=bills.id
			ORDER BY date DESC, id DESC
			LIMIT 1
		) AS status
		FROM bills
		LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
		LEFT JOIN sessions
			ON bills.session_id = sessions.id
		WHERE DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY) <= bills.date_introduced
		ORDER BY bills.date_introduced DESC, bills.id DESC';

$result = mysqli_query($GLOBALS['db'], $sql);
$num_results = mysqli_num_rows($result);
if ($num_results > 0)
{
    $page_body .= '<p>' . $num_results . ' bill' . ($num_results > 1 ? 's' : '') . ' found.</p>';
    $date = '';
    $i=0;
    while ($bill = mysqli_fetch_assoc($result))
    {
        $bill = array_map('stripslashes', $bill);
        if ($bill['date_introduced'] != $date)
        {
            if ($i > 0)
            {
                $page_body .= '</ul>';
            }
            $date = $bill['date_introduced'];
            $page_body .= '<h2>' . $date . '</h2>
				<ul>';
        }
        $page_body .= '
				<li><a href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/" class="balloon">' . mb_strtoupper($bill['number']) . balloon($bill, 'bill') . '</a>: ' .
             $bill['catch_line'] . '</li>';
        $i++;
    }
    $page_body .= '</ul>';
}

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Options</h3>
		View the past . . .
		<ul>
			<li><a href="/bills/introduced/3/">3 Days</a></li>
			<li><a href="/bills/introduced/7/">7 Days</a></li>
			<li><a href="/bills/introduced/14/">14 Days</a></li>
			<li><a href="/bills/introduced/21/">21 Days</a></li>
			<li><a href="/bills/introduced/45/">45 Days</a></li>
			<li><a href="/bills/introduced/60/">60 Days</a></li>
		</ul>
	</div>

	<div class="box">
		<h3>Explanation</h3>
		<p>The very first step in changing the law is introducing a bill. This is a list of every
		bill that has been introduced in the past ' . $days . ' days.</p>
	</div>';

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
