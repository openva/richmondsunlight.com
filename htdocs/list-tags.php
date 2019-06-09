<?php

###
# Tags Listing Page
#
# PURPOSE
# Displays tag clouds for a given grouping--year, party, chamber, committee, legislator, etc.
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# LOCALIZE VARIABLES
if (!empty($_GET['year']))
{
    $year = mysqli_real_escape_string($GLOBALS['db'], $_GET['year']);
}
elseif (!empty($_GET['party']))
{
    $party = mysqli_real_escape_string($GLOBALS['db'], $_GET['committee']);
}
else
{
    $year = SESSION_YEAR;
    $session_suffix = SESSION_SUFFIX;
}

# PAGE METADATA
$page_title = $year . ' Bills by Topic';
if (!empty($party))
{
    $page_title .= ', Introduced by ' . ucfirst($party);
}
$site_section = 'bills';

# PAGE CONTENT
$sql = 'SELECT COUNT(*) AS count, tags.tag
		FROM tags LEFT JOIN bills
		ON tags.bill_id = bills.id
		LEFT JOIN sessions
		ON bills.session_id=sessions.id
		WHERE sessions.year=' . $year . '
		GROUP BY tags.tag
		HAVING count > 1
		ORDER BY tag ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
$tag_count = mysqli_num_rows($result);
if ($tag_count > 0)
{
    $page_body .= '
	<div class="tags">';
    # Build up an array of tags, with the key being the tag and the value being the count.
    while ($tag = mysqli_fetch_array($result))
    {
        $tag = array_map('stripslashes', $tag);
        $tags[$tag{tag}] = $tag['count'];
    }

    # Sort the tags in reverse order by key (their count), shave off the top 30, and then
    # resort alphabetically.
    ksort($tags);

    foreach ($tags as $tag => $count)
    {
        $font_size = round(log($count), 2);
        if ($font_size < 1)
        {
            $font_size = 1;
        }
        $page_body .= '<span style="font-size: ' . $font_size . 'em;"><a href="/bills/tags/' . urlencode($tag) . '/">' . $tag . '</a></span> ';
    }
    $page_body .= '
	</div>';
}
else
{
    $page_body = '<p>No bills have yet been filed for the ' . $year . ' session.</p>';
}

# SIDEBAR
$page_sidebar = '
	<div class="box">
		<h3>Explanation</h3>
		<p>This is a “tag cloud,” a sort of a graph of the topics addressed by these bills. Each
		word represents a different topic. The bigger the word is, the more bills that have been
		filed on that topic. A tiny word might represent just have a couple of bills, while a huge
		one (like “commendations”) might represent dozens of bills.</p>

		<p>All of these tags have been created by visitors to Richmond Sunlight—people just like
		you.</p>
	</div>';

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
