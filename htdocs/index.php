<?php

###
# Index Page
#
# PURPOSE
# The front page of the site.
#
###

# INCLUDES
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# INITIALIZE SESSION
session_start();

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Welcome to Richmond Sunlight';
$browser_title = 'Tracking the Virginia General Assembly';
$site_section = 'home';

# PAGE CONTENT
$page_body = '<p>The 2020 Virginia General Assembly session will begin on January 8, and
			continue for 60 days. Here you can read <a href="/bills/">the bills that are
			proposed</a>, follow them as they’re voted on, and learn about the few that
			ultimately become law.</p>';

$sql = 'SELECT COUNT(*) AS count, tags.tag
		FROM tags
		LEFT JOIN bills
			ON tags.bill_id = bills.id
		WHERE bills.session_id=' . SESSION_ID . '
		GROUP BY tags.tag
		HAVING count > 10
		ORDER BY tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
$tag_count = mysqli_num_rows($result);
if ($tag_count > 0)
{
    $page_body .= '
	<h2>Bill Topics</h2>
	<div class="tags">';
    # Build up an array of tags, with the key being the tag and the value being the count.
    while ($tag = mysqli_fetch_array($result))
    {
        $tag = array_map('stripslashes', $tag);
        $tags[$tag{'tag'}] = $tag['count'];
    }

    # Sort the tags in reverse order by key (their count), shave off the top 30, and then
    # resort alphabetically.
    arsort($tags);
    $tags = array_slice($tags, 0, 75);
    ksort($tags);

    # Establish a scale -- the average size in this list should be 1.25em, with the scale
    # moving up and down from there.
    $multiple = 1.25 / (array_sum($tags) / count($tags));

    foreach ($tags as $tag => $count)
    {
        $size = round(($count * $multiple), 1);
        if ($size > 4)
        {
            $size = 4;
        }
        elseif ($size < .75)
        {
            $size = .75;
        }

        $page_body .= '<span style="font-size: ' . $size . 'em;"><a href="/bills/tags/' . urlencode($tag) . '/">' . $tag . '</a></span> ';
    }
    $page_body .= '
	</div>';
}

# Show all bills, with a hotness greater than or equal to 10, that have recently hit progress
# milestones.
$sql = 'SELECT bills.number, bills.catch_line, bills.hotness, bills_status.status,
		bills_status.translation AS status_translation
		FROM bills_status
		LEFT JOIN bills
			ON bills_status.bill_id = bills.id
		WHERE bills.session_id =14
		AND (bills_status.translation = "passed house"
			OR bills_status.translation = "passed senate"
			OR bills_status.translation = "passed committee"
			OR bills_status.translation = "failed committee"
			OR bills_status.translation = "failed house"
			OR bills_status.translation = "failed senate")
		AND DATEDIFF( NOW( ) , bills_status.date ) <=5
		AND interestingness >= 100
		ORDER BY DATE DESC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '<div id="updates">
					<h2>Interesting Bill Updates</h2>
					<table>';
    while ($bill = mysqli_fetch_array($result))
    {
        $bill['url'] = '/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/';
        $page_body .= '<tr>
						<td><a href="' . $bill['url'] . '" class="balloon">' . mb_strtoupper($bill['number']) . '</td>
						<td>' . $bill['catch_line'] . '</td>
						<td>' . $bill['status_translation'] . '</td>
					</tr>';
    }
    $page_body .= '</table></div>';
}

# Newest Comments
$sql = 'SELECT comments.id, comments.bill_id, comments.date_created AS date,
		comments.name, comments.email, comments.url, comments.comment,
		comments.type, bills.number AS bill_number, bills.catch_line AS bill_catch_line,
		sessions.year,
			(
			SELECT COUNT(*)
			FROM comments
			WHERE bill_id=bills.id AND status="published"
			AND date_created <= date
			) AS number
		FROM comments
		LEFT JOIN bills
			ON bills.id=comments.bill_id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE comments.status="published"
		ORDER BY comments.date_created DESC
		LIMIT 6';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_body .= '
	<div id="newest-comments">
		<h2>Newest Comments</h2>';
    while ($comment = mysqli_fetch_array($result))
    {
        $comment = array_map('stripslashes', $comment);
        if (mb_strlen($comment['comment']) > 200)
        {
            $comment['comment'] = preg_replace('#<blockquote>(.*)</blockquote>#D', '', $comment['comment']);
            $comment['comment'] = strip_tags($comment['comment']);
        }
        $page_body .= '<a href="/bill/' . $comment['year'] . '/' . $comment['bill_number'] . '/#comment-' . $comment['number'] . '">
				<div><strong>' . $comment['bill_catch_line'] . '</strong><br />
				' . $comment['name'] . ' writes:
				' . $comment['comment'] . '</div></a>';
    }
    $page_body .= '
		</div>';
}


$page_sidebar = '';

# Session Stats
$sql = 'SELECT chamber, COUNT(*) AS count
		FROM bills
		WHERE session_id=' . SESSION_ID . '
		GROUP BY chamber';
$result = mysqli_query($GLOBALS['db'], $sql);
while ($stats = mysqli_fetch_array($result))
{
    if ($stats['chamber'] == 'house')
    {
        $session['house_count'] = $stats['count'];
    }
    elseif ($stats['chamber'] == 'senate')
    {
        $session['senate_count'] = $stats['count'];
    }
}

$page_sidebar .= '
	<h3>Total Bills Filed</h3>
	<div class="box stats" id="bills-left">
		<p id="house-bill-count">
			<a href="/bills/#house" style="text-decoration: none;">
			<span class="stat-number">' . number_format($session['house_count']) . '</span>
			</a>
		</p>

		<p id="senate-bill-count">
			<a href="/bills/#senate" style="text-decoration: none;">
			<span class="stat-number">' . number_format($session['senate_count']) . '</span>
			</a>
		</p>
	</div>';

# Most interesting bills
$sql = 'SELECT bills.number, bills.catch_line,
		DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
		representatives.name AS patron, bills.status, bills.hotness
		FROM bills
		LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
		WHERE bills.session_id = ' . SESSION_ID . '
		ORDER BY bills.hotness DESC
		LIMIT 5';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0)
{
    $page_sidebar .= '
		<h3>Today’s Most Interesting Bills</h3>
		<div class="box" id="interesting">
			<ul>';
    while ($bill = mysqli_fetch_array($result))
    {
        $bill = array_map('stripslashes', $bill);
        $page_sidebar .= '
			<li><a href="/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/" class="balloon">' . mb_strtoupper($bill['number']) . balloon($bill, 'bill') . '</a>: ' . $bill['catch_line'] . '</li>
		';
    }
    $page_sidebar .= '
			</ul>
		</div>';
}

# Newest Bills
if (IN_SESSION == 'y')
{
    $sql = 'SELECT bills.number, bills.catch_line, sessions.year,
			DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
			representatives.name AS patron,
			(
				SELECT status
				FROM bills_status
				WHERE bill_id=bills.id
				ORDER BY date DESC, id DESC
				LIMIT 1
			) AS status
			FROM bills
			LEFT JOIN sessions
				ON bills.session_id=sessions.id
			LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
			ORDER BY bills.date_introduced DESC, bills.id DESC
			LIMIT 5';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) > 0)
    {
        $page_sidebar .= '
			<h3>Newest Bills</h3>
			<div class="box" id="newest">
				<ul>';
        while ($bill = mysqli_fetch_array($result))
        {
            $bill = array_map('stripslashes', $bill);
            $bill['summary'] = mb_substr($bill['summary'], 0, 175) . '...';
            $page_sidebar .= '
				<li><a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number']) . '/" class="balloon">' . $bill['number'] . balloon($bill, 'bill') . '</a>: ' . $bill['catch_line'] . '</li>
			';
        }
        $page_sidebar .= '
				</ul>
			</div>';
    }
}

$page_sidebar .= '
		<h3>Keep Up With Us</h3>
		<div class="box" id="social-networking" style="text-align: center;">

			<p><a href="http://twitter.com/richmond_sun"><img src="/images/twitter.gif" width="100"
				height="31" alt="Twitter" /></a></p>

		</div>';


$html_head = '
<script type="application/ld+json">
{
   "@context": "http://schema.org",
   "@type": "WebSite",
   "url": "https://www.richmondsunlight.com/",
   "potentialAction": {
     "@type": "SearchAction",
     "target": "https://www.richmondsunlight.com/search/?q={search_term_string}",
     "query-input": "required name=search_term_string"
   }
}
</script>';

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->browser_title = $browser_title;
$page->assemble();
$page->display();
