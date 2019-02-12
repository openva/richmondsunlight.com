<?php

###
# Bills' Activity
#
# PURPOSE
# Lists the bill activity in the past X days.
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
$days = mysql_real_escape_string($_REQUEST['days']);
if (empty($days))
{
    $days = 3;
}
elseif (!is_numeric($days))
{
    $days = 3;
}

# PAGE METADATA
$page_title = 'Bill Activity in Past ' . $days . ' Days';
$site_section = 'bills';

# PAGE CONTENT

# Select the status of the bills.
$sql = 'SELECT bills.number, sessions.year, bills.catch_line, bills_status.status,
		DATE_FORMAT(bills_status.date, "%M %d, %Y") AS date, representatives.name AS patron,
		bills.date_introduced, bills_status.lis_vote_id, votes.total AS vote_count,
		(
			SELECT status
			FROM bills_status
			WHERE bill_id=bills.id
			ORDER BY date DESC, id DESC
			LIMIT 1
		) AS status
		FROM bills_status
		LEFT JOIN bills
		ON bills.id = bills_status.bill_id
		LEFT JOIN sessions
		ON bills.session_id = sessions.id
		LEFT JOIN representatives
		ON bills.chief_patron_id = representatives.id
		LEFT JOIN votes
		ON bills_status.lis_vote_id=votes.lis_id
		WHERE DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY) <= bills_status.date
		ORDER BY bills_status.date DESC';

$result = mysql_query($sql);
$num_results = mysql_num_rows($result);
if ($num_results > 0)
{
    $page_body .= '<p>' . number_format($num_results) . ' action' . ($num_results > 1 ? 's' : '') . ' found.</p>';
    $date = '';
    $i=0;
    while ($bill = mysql_fetch_array($result))
    {
        $bill = array_map('stripslashes', $bill);
        if ($bill['date'] != $date)
        {
            $date = $bill['date'];
            if ($i > 0)
            {
                $page_body .= '</ul>';
            }
            $page_body .= '<h2>' . $date . '</h2>
			<ul>';
        }
        $page_body .= '
				<li><a href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/" class="balloon">' . mb_strtoupper($bill['number']) . balloon($bill, 'bill') . '</a>: ' .
             $bill['catch_line'] . '</li>
				<ul>
					<li>' . ((!empty($bill['lis_vote_id']) && ($bill['vote_count'] > 0)) ? '<a href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/' . mb_strtolower($bill['lis_vote_id']) . '/">' : '') . $bill['status'] . ((!empty($bill['lis_vote_id']) && ($bill['vote_count'] > 0)) ? '</a>' : '') . '</li>
				</ul>';
        $i++;
    }
    $page_body .= '</ul>';
}

# PAGE SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Options</h3>
		View the past...
		<ul>
			<li><a href="/bills/activity/3/">3 Days</a></li>
			<li><a href="/bills/activity/7/">7 Days</a></li>
			<li><a href="/bills/activity/14/">14 Days</a></li>
			<li><a href="/bills/activity/21/">21 Days</a></li>
			<li><a href="/bills/activity/45/">45 Days</a></li>
			<li><a href="/bills/activity/60/">60 Days</a></li>
		</ul>
	</div>

	<div class="box">
		<h3>Explanation</h3>
		<p>There are many steps between the introduction of a bill and when (if) it becomes law.
		At left is every individual step taken by all bills in the past ' . $days . ' days.  This also
		gives an idea of what the General Assembly is up to every day, even when they\'re not in
		session.  Some days no committees or subcommittees meet, some days there\'s a lot
		going on.</p>
	</div>';

# OUTPUT THE PAGE
/*display_page('page_title='.urlencode($page_title).'&page_body='.urlencode($page_body).
    '&page_sidebar='.urlencode($page_sidebar).'&site_section='.urlencode($site_section));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
