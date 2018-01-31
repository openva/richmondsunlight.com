<?php

###
# List Committees
#
# PURPOSE
# List all committees.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/settings.inc.php');
include_once('includes/functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# PAGE METADATA
$page_title = 'Committees';
$site_section = 'committees';

# PAGE SIDEBAR
$page_sidebar = '

		<div class="box">
			<h3>Explanation</h3>
			<p>The House of Delegates and the Senate can’t possibly consider every bill that’s
			introduced each year—there are just too many.  So bills are assigned to the
			committees most appropriate to them, and those committees act as a filter, weeding
			out the bad bills and sending the good bills on to be considered by the entire body.
			 (At least, that’s the theory.)</p>

			<p>The House has 14 committees, and the Senate has 11.</p>

			<p>Committees are where much of the real work of the General Assembly gets done.
			Committee meetings are where people can testify about bills, where deals are made,
			and legislators speak more freely than they tend to before their entire body.
			They’re held in rooms that are large enough that they’re not intimidating for
			regular folks to show up at, and the meetings tend to be simple enough that it’s not
			hard to follow what’s going on. <a
			href="http://legis.state.va.us/1_cit_guide/tips_testify.html">You can testify at a
			committee meeting!</a></p>
		</div>
';

# PAGE CONTENT
$page_content = '';

# Retrieve the senate committee info from the database
$sql = 'SELECT id, shortname, name, chamber, meeting_time,
			(SELECT COUNT(*)
			 FROM bills
			 WHERE session_id='.SESSION_ID.'
			 AND last_committee_id=committees.id
			 AND current_chamber=committees.chamber
			 AND status != "failed" AND status != "continued" AND status != "approved"
			 AND status != "passed '.$committee['chamber'].'" AND status != "passed"
			 AND status != "vetoed") AS count
		FROM committees
		WHERE chamber="senate" AND parent_id IS NULL
		ORDER BY name ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
		$page_body .= '
					<div class="right_side">
						<h2>Senate</h2>
						<ul>';
	while ($committee = mysql_fetch_array($result))
	{
		$committee = array_map('stripslashes', $committee);
		$page_body .= '<li><a href="/committee/senate/'.$committee['shortname'].'/">'.$committee['name'].'</a>';
		if ($committee['count'] > 0)
		{
			$page_body .= ' (<a href="/bills/committee/'.$committee['chamber'].'/'.$committee['shortname'].'/"
			title="Bills before this committee">'.$committee['count'].'</a>)';
		}
		$page_body .= '</li>';
	}
	$page_body .= '
						</ul>
					</div>';
}

# Retrieve the house committee info from the database
$sql = 'SELECT id, shortname, name, chamber, meeting_time,
			(SELECT COUNT(*)
			 FROM bills
			 WHERE session_id='.SESSION_ID.'
			 AND last_committee_id=committees.id
			 AND current_chamber=committees.chamber
			 AND status != "failed" AND status != "continued" AND status != "approved"
			 AND status != "passed '.$committee['chamber'].'" AND status != "passed"
			 AND status != "vetoed") AS count
		FROM committees
		WHERE chamber="house" AND parent_id IS NULL
		ORDER BY name ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
		$page_body .= '
					<div class="left_side">
						<h2>House</h2>
						<ul>';
	while ($committee = mysql_fetch_array($result))
	{
		$committee = array_map('stripslashes', $committee);
		$page_body .= '<li><a href="/committee/house/'.$committee['shortname'].'/">'.$committee['name'].'</a>';
		if ($committee['count'] > 0)
		{
			$page_body .= ' (<a href="/bills/committee/'.$committee['chamber'].'/'.$committee['shortname'].'/"
			title="Bills before this committee">'.$committee['count'].'</a>)';
		}
		$page_body .= '</li>';
	}
	$page_body .= '
						</ul>
					</div>';
}

# OUTPUT THE PAGE
/*display_page('page_title='.$page_title.'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
	'&site_section='.urlencode($site_section));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();

?>
