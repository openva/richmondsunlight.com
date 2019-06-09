<?php

###
# Photosynthesis Public Portfolio Listing
#
# PURPOSE
# Lists the contents of a single public portfolio.
#
# NOTES
# None.
#
# TODO
# None.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once '../includes/functions.inc.php';
include_once '../includes/settings.inc.php';
include_once '../includes/photosynthesis.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# PAGE METADATA
$page_title = 'Photosynthesis';
$site_section = 'photosynthesis';

# LOCALIZE VARIABLES
$hash = $_GET['hash'];

# ADDITIONAL HTML HEADERS
$html_head = '<link rel="alternate" type="application/rss+xml" title="RSS 0.92" href="/photosynthesis/rss/portfolio/' . $hash . '/" />';

# INITIALIZE SESSION
session_start();

# If the user is logged in, get his data.
if (@logged_in())
{
    $user = @get_user();
}

# Start displaying the main page.
$page_body = '';

# Get this portfolio's basic data.
$sql = 'SELECT dashboard_portfolios.id, dashboard_portfolios.hash, dashboard_portfolios.name,
		dashboard_portfolios.notes, users.name AS user_name, dashboard_user_data.organization,
		users.url
		FROM dashboard_portfolios
		LEFT JOIN users
			ON dashboard_portfolios.user_id = users.id
		LEFT JOIN dashboard_user_data
			ON users.id = dashboard_user_data.user_id
		WHERE dashboard_portfolios.public = "y" AND dashboard_portfolios.hash="' . $hash . '"';
$result = mysqli_query($GLOBALS['db'], $sql);

# If this portfolio doesn't exist or isn't visible.
if (mysqli_num_rows($result) == 0)
{
    die('Invalid ID.');
}

# If this portfolio does exist.
else
{
    $portfolio = mysqli_fetch_array($result);
    $portfolio = array_map('stripslashes', $portfolio);

    # Increment the view count.
    $sql = 'UPDATE dashboard_portfolios
			SET view_count = view_count + 1
			WHERE id = ' . $portfolio['id'];
    mysqli_query($GLOBALS['db'], $sql);

    # Make the user closer to anonymous.
    $tmp = explode(' ', $portfolio['user_name']);
    if (count($tmp) > 1)
    {
        $portfolio['user_name'] = $tmp[0] . ' ' . $tmp[1]{0} . '.';
    }
    else
    {
        $portfolio['user_name'] = $tmp[0];
    }

    # Set the page title to the user's name.
    if (!empty($portfolio['organization']))
    {
        $page_title .= ' &raquo; ' . $portfolio['organization'];
    }
    else
    {
        $page_title .= ' &raquo; ' . $portfolio['user_name'];
    }
    $page_title .= '’s Portfolio';

    # Establish a sidebar.
    $page_sidebar = '
		<h3>About This Portfolio</h3>
		<div class="box">
			<p>This is a collection of bills being tracked by ';

    # Make the user's name the link, unless there's an organization, in which case it should
    # be on that.
    if (empty($portfolio['url']))
    {
        $page_sidebar .= $portfolio['user_name'] . ' ';
    }
    elseif (empty($portfolio['organization']))
    {
        $page_sidebar .= '<a href="' . $portfolio['url'] . '">' . $portfolio['user_name'] . '</a>';
    }
    else
    {
        $page_sidebar .= $portfolio['user_name'] . ' ';
    }
    if (!empty($portfolio['url']) && !empty($portfolio['organization']))
    {
        $page_sidebar .= ' for <a href="' . $portfolio['url'] . '">' . $portfolio['organization'] . '</a>';
    }
    elseif (!empty($portfolio['organization']))
    {
        $page_sidebar .= ' for ' . $portfolio['organization'];
    }
    $page_sidebar .= ' using the Photosynthesis bill-tracking tool.</p>

			<p><a href="/photosynthesis/">Create a Photosynthesis account today</a> and
			keep track of the legislation that interests you!</p>

			<script src="https://connect.facebook.net/en_US/all.js#xfbml=1"></script>
			<fb:like layout="button_count" show_faces="false" width="100" action="recommend"></fb:like>

		</div>';

    # Display a tag cloud.
    $sql = 'SELECT COUNT(*) AS count, tags.tag
			FROM tags
			LEFT JOIN dashboard_bills
				ON tags.bill_id = dashboard_bills.bill_id
			LEFT JOIN bills
				ON dashboard_bills.bill_id=bills.id
			LEFT JOIN sessions
				ON bills.session_id = sessions.id
			WHERE dashboard_bills.portfolio_id = ' . $portfolio['id'] . '
			AND sessions.year=' . SESSION_YEAR . '
			GROUP BY tags.tag
			ORDER BY tags.tag ASC';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) > 0)
    {
        $page_sidebar .= '
		<a href="javascript:openpopup(\'/help/tag-clouds/\')" title="Help"><img src="/images/help-gray.gif" class="help-icon" alt="?" /></a>
		<h3>These Bills Are About .&thinsp;.&thinsp;.</h3>
		<div class="box">
			<div class="tags">';
        $top_tag = 1;
        $top_tag_size = 3;
        while ($tag = mysqli_fetch_array($result))
        {
            $tags[] = array_map('stripslashes', $tag);
            if ($tag['count'] > $top_tag)
            {
                $top_tag = $tag['count'];
            }
        }
        if ($top_tag == 1)
        {
            $top_tag_size = 1;
        }
        for ($i=0; $i<count($tags); $i++)
        {
            $font_size = round(($tags[$i]['count'] / $top_tag * $top_tag_size), 2);
            if ($font_size >= '.75')
            {
                $page_sidebar .= '<span style="font-size: ' . $font_size . 'em;">
					<a href="/bills/tags/' . urlencode($tags[$i]['tag']) . '/">' . $tags[$i]['tag'] . '</a>
				</span>';
            }
        }
        $page_sidebar .= '
			</div>
		</div>';
    }


    # List all of the bills in this portfolio.
    $sql = 'SELECT bills.id, bills.number, bills.session_id, bills.chamber,
			bills.catch_line, bills.summary, bills.status, bills.outcome, sessions.year,
			representatives.name_formatted AS patron, representatives.shortname AS patron_shortname,
				(SELECT COUNT(*)
				FROM comments
				WHERE status = "published" AND bill_id = bills.id) AS comments,
			dashboard_bills.notes
			FROM bills
			LEFT JOIN sessions
				ON bills.session_id = sessions.id
			LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
			LEFT JOIN dashboard_bills
				ON dashboard_bills.bill_id = bills.id
			WHERE dashboard_bills.portfolio_id = ' . $portfolio['id'] . '
			AND sessions.year=' . SESSION_YEAR . '
			ORDER BY bills.chamber DESC,
			SUBSTRING(bills.number FROM 1 FOR 2) ASC,
			CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
    $result = mysqli_query($GLOBALS['db'], $sql);
    $bill_count = mysqli_num_rows($result);
    if ($bill_count == 0)
    {
        die('Empty portfolio.');
    }

    # We've found bills in this portfolio.
    else
    {
        $page_body = '<div id="public-portfolio">';
        if ($bill_count == 1)
        {
            $page_body .= '<p><em>Just one bill is being tracked.</em></p>';
        }
        else
        {
            $page_body .= '<p><em>' . $bill_count . ' bills are being tracked.</em></p>';
        }

        while ($bill = mysqli_fetch_array($result))
        {
            $bill = array_map('stripslashes', $bill);

            # Remove the bit of the bill summary that just duplicates the catch line.
            $bill['summary'] = str_replace($bill['catch_line'], '', $bill['summary']);

            # Optionally establish a modifying status that will affect the look of the whole
            # bill in the portfolio.
            if ($bill['outcome'] == 'dead')
            {
                $bill['status_class'] = ' dead';
            }
            else
            {
                $bill['status_class'] = '';
            }

            $page_body .= '
			<div class="bill' . $bill['status_class'] . '">
				<h4><a href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/">' . $bill['catch_line']
                . ' (' . mb_strtoupper($bill['number']) . ')</a></h4>
				<p>Patron: <a href="/legislator/' . $bill['patron_shortname'] . '/">'
                    . $bill['patron'] . '</a><br />
				Status: ' . $bill['status'] . '</p>';

            # If the portfolio creator has provided notes on this bill.
            if (!empty($bill['notes']))
            {
                $page_body .= '
				<div class="notes">
					' . nl2p($bill['notes']) . '
				</div>';
            }
            $page_body .= '<p class="comments">';
            if ($bill['comments'] == 0)
            {
                $page_body .= 'Be the first to comment on this bill';
            }
            else
            {
                if ($bill['comments'] == 1)
                {
                    $page_body .= 'One person has commented on this bill';
                }
                else
                {
                    $page_body .= 'There are ' . $bill['comments'] . ' comments about this bill';
                }
            }
            $page_body .= ' <a href="/bill/' . $bill['year'] . '/' . mb_strtolower($bill['number'])
                . '/#comments">&raquo;</a></p>
			</div>';
        }

        $page_body .= '</div>';
    }
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
