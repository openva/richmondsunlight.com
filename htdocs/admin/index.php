<?php

###
# Dashboard
#
# PURPOSE
# An administrative overview of the goings-on of Richmond Sunlight.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once $_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Dashboard';
$site_section = 'admin';

# PAGE CONTENT

/*
 * If there's an operation to perform prior to loading the page
 */
if (isset($_GET['op']))
{

	$op = $_GET['op'];
	$user_id = $_GET['user_id'];

	/*
	 * Delete a user
	 */
	if ( $op == 'delete' && !empty($user_id) )
	{
		$user = new User;
		$user->id=$user_id;
		$user->delete();
	}
}

$page_body = '
		<div>
			<a href="/admin/comments/">Comments</a> |
			<a href="/admin/tag.php">Tag Bills</a> |
			<a href="/admin/video/">Video</a> |
			<a href="apc.php">APC</a> |
			<a href="memcache.php">Memcached</a> |
			<a href="districts.php">Districts</a>
		</div>';

# Select the tags from the past 3 days that were not added by me.
$sql = 'SELECT tags.id, tags.tag, bills.number AS bill, sessions.year, users.name AS author
		FROM tags
		LEFT JOIN bills
			ON tags.bill_id=bills.id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		LEFT JOIN users
			ON tags.user_id=users.id
		WHERE DATE_SUB(CURDATE(), INTERVAL 3 DAY) <= tags.date_created
		AND users.trusted = "n"
		ORDER BY tags.date_created DESC, bills.id DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
	$page_body .= '
		<h2>Recent Tags</h2>
		<p>The following tags have been applied to bills in the last three days by non-trusted
		users.</p>';
    while ($tag = mysqli_fetch_array($result))
    {
        $tag = array_map('stripslashes', $tag);
        $tag['bill'] = strtolower($tag['bill']);
        $page_body .= '<div class="tag"><a href="/bill/'.$tag['year'].'/'.$tag['bill'].'/" title="'
            .$tag['author'].'">'.$tag['tag'].'</a> <a href="/process-tags.php?delete='
            .$tag['id'].'" class="delete">✕</a></div>';
    }
}

# Select the new users from the past month.
$sql = 'SELECT name, id, url
		FROM users
		WHERE DATE_SUB(CURDATE(), INTERVAL 30 DAY) <= date_created
		AND name IS NOT NULL
		ORDER BY date_created DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<script>
		$(document).ready(function(){
			$(".user_delete").click(function(){
				return confirm("Are you sure you want to delete this user?");
			});
		});
		</script>
		<style>
			.user, .tag {
				display: inline-block;
				background-color: #f4eee5;
				padding: 2px 6px;
				margin: 3px;
				border-radius: 5px;
			}
				.tag a {
					text-decoration: none;
					color: #333;
					font-weight: normal;
				}
				a.user_delete, .tag .delete {
					font-family: arial;
					color: black;
					font-weight: 800;
					font-size: .75em;
					margin-left: .5em;
					text-decoration: none;
				}
		</style>
		
		<h2>Recent Registrants</h2>
		<p>The following people have signed up in the past 30 days.</p>';
    while ($user = mysqli_fetch_assoc($result))
    {
		$user = array_map('stripslashes', $user);
		$page_body .= '<div class="user">';
        if (!empty($user['url']))
        {
            $page_body .= '<a href="'.$user['url'].'">';
        }
        $page_body .= $user['name'];
        if (!empty($user['url']))
        {
            $page_body .= '</a> ';
		}
		$page_body .= '<a href="?op=delete&amp;user_id=' . $user['id'] . '" class="user_delete">✕</a>';
        $page_body .= '</div>';
    }
}

# Select the number of comments for the past seven days.
$sql = 'SELECT DATE_FORMAT(date_created, "%Y-%m-%d") AS date, COUNT(*) AS number
		FROM comments
		GROUP BY date
		ORDER BY date DESC
		LIMIT 7';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<h2>Comments by Day</h2>
		<p>This is the number of comments, by day. Only lists the last seven days for which
		there were any comments.</p>
		<table class="sortable" id="comments">
			<thead><tr><th>Day</th><th>#</th></tr></thead>
			<tbody>';
    while ($day = mysqli_fetch_array($result))
    {
        $page_body .= '
			<tr><td>'.$day['date'].'</td><td>'.$day['number'].'</td></tr>';
    }
    $page_body .= '
			</tbody>
		</table>';
}

# Select the number of poll votes for the past seven days.
$sql = 'SELECT DATE_FORMAT(date_created, "%Y-%m-%d") AS date, COUNT(*) AS number
		FROM polls
		GROUP BY date
		ORDER BY date DESC
		LIMIT 7';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<h2>Poll Votes by Day</h2>
		<p>This is the number of votes cast in polls, by day. Only lists the last seven days for
		which there were any poll votes.</p>
		<table id="poll">
			<thead><tr><th>Day</th><th>#</th></tr></thead>
			<tbody>';
    while ($day = mysqli_fetch_array($result))
    {
        $page_body .= '
			<tr><td>'.$day['date'].'</td><td>'.$day['number'].'</td></tr>';
    }
    $page_body .= '
			</tbody>
		</table>';
}

# Select the number of comment subscriptions for the past seven days.
$sql = 'SELECT DATE_FORMAT(date_created, "%Y-%m-%d") AS date, COUNT(*) AS number
		FROM comments_subscriptions
		GROUP BY date
		ORDER BY date DESC
		LIMIT 7';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<h2>Comment Subscriptions by Day</h2>
		<p>This is the number of new subscriptions to discussions, by day. Only lists the last
		seven days for which there were any new subscriptions.</p>
		<table id="comment-subscriptions">
			<thead><tr><th>Day</th><th>#</th></tr></thead>
			<tbody>';
    while ($day = mysqli_fetch_array($result))
    {
        $page_body .= '
			<tr><td>'.$day['date'].'</td><td>'.$day['number'].'</td></tr>';
    }
    $page_body .= '
			</tbody>
		</table>';
}

# Select the newest Photosynthesis registrants.
$sql = 'SELECT organization AS name, dashboard_portfolios.hash AS url
		FROM dashboard_user_data
		LEFT JOIN dashboard_portfolios
			ON dashboard_user_data.user_id=dashboard_portfolios.user_id
		WHERE organization IS NOT NULL
		ORDER BY dashboard_user_data.date_created DESC
		LIMIT 10';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<h2>Newest Photosynthesis Organizations</h2>
		<p>These are the last ten organizations to sign up for Photosynthesis.</p>
		<p>';
    while ($organization = mysqli_fetch_array($result))
    {
        $organization = array_map('stripslashes', $organization);
        $page_body .= '<a href="/photosynthesis/'.$organization['url'].'/">'
            .$organization['name'] .'</a>, ';
    }
    $page_body .= '</p>';
}

# Show top bill-view IPs in the past day
$sql = 'SELECT ip, COUNT(*) AS number
		FROM bills_views
		WHERE DATE_SUB(CURDATE(), INTERVAL 1 HOUR) <= date
		GROUP BY ip
		ORDER BY number DESC
		LIMIT 5';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<h2>IPs with the Most Bill Views</h2>
		<p>These are the IPs that have viewed the largest number of bills in the past hour</p>
		<table class="sortable" id="popular-bills">
			<thead><tr><th>IP</th><th>Views</th>
			<tbody>';
    while ($viewer = mysqli_fetch_array($result))
    {
        $page_body .= '
			<tr>
				<td>'.$viewer['ip'].'</td>
				<td>'.$viewer['number'].'</td>
			</tr>';
    }
    $page_body .= '
			</tbody>
		</table>';
}

# Select the most popular bills of the past X days.
if (LEGISLATIVE_SEASON == true)
{
    $days = 3;
}
else
{
    $days = 14;
}
$sql = 'SELECT bills.number, bills.catch_line, sessions.year, COUNT(*) AS views
		FROM bills_views
		LEFT JOIN bills
			ON bills.id=bills_views.bill_id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY) <= bills_views.date
		GROUP BY bills_views.bill_id
		ORDER BY views DESC
		LIMIT 10';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
		<h2>Most Popular Bills</h2>
		<p>These are the bills that have had the most views in the past '. $days . ' days.</p>
		<table class="sortable" id="popular-bills">
			<thead><tr><th>Year</th><th>Title</th><th>Views</th></tr></thead>
			<tbody>';
    while ($bill = mysqli_fetch_array($result))
    {
        $bill = array_map('stripslashes', $bill);
        $bill['number'] = strtolower($bill['number']);
        $page_body .= '
			<tr>
				<td>'.$bill['year'].'</td>
				<td><a href="/bill/'.$bill['year'].'/'.$bill['number'].'/">'
                .strtoupper($bill['number']).': '.$bill['catch_line'].'</a></td>
				<td>'.$bill['views'].'</td>
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
$page->html_head = $html_head;
$page->process();
