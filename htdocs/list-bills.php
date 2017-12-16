<?php

###
# Bills Listing Page
# 
# PURPOSE
# Lists all current bills.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('settings.inc.php');
include_once('functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# LOCALIZE VARIABLES
if (!empty($_GET['tag']))
{
	$tag = mysql_real_escape_string(urldecode($_GET['tag']));
}
elseif (!empty($_GET['year']))
{
	$year = mysql_real_escape_string($_GET['year']);
}
elseif (!empty($_GET['committee']) && !empty($_GET['chamber']))
{
	$committee = mysql_real_escape_string($_GET['committee']);
	$chamber = mysql_real_escape_string($_GET['chamber']);
}
else
{
	$year = SESSION_YEAR;
	$session_suffix = SESSION_SUFFIX;
}
if (!empty($_GET['status']))
{
	if (($_GET['status'] == 'passed') || ($_GET['status'] == 'failed'))
	{
		$status = mysql_real_escape_string($_GET['status']);
	}
}
if (!empty($_GET['session_suffix']))
{
	$session_suffix = $_GET['session_suffix']+0;
}
if (!empty($_GET['tagless']))
{
	$tagless = TRUE;
}

# PAGE METADATA
if (!empty($tag))
{
	$page_title = SESSION_YEAR.' Bills Tagged with “'.ucwords($tag).'”';
}
else
{
	$page_title = $year.' Bills';
	if (!empty($status))
	{
		$page_title .= ' That '.ucfirst($status);
	}
}
$site_section = 'bills';

# PAGE CONTENT

# If we're searching by tag.
if (!empty($tag))
{

	# Select all bills from the database.
	$sql = 'SELECT bills.number, bills.chamber, bills.status AS status_raw,
			bills.catch_line, representatives.name AS patron, sessions.year,
			bills.date_introduced, bills.status
			FROM bills
			LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
			LEFT JOIN sessions
				ON bills.session_id = sessions.id
			LEFT JOIN tags
				ON bills.id = tags.bill_id
			WHERE sessions.id = bills.session_id';
	if ($tag == 'untagged')
	{
		$sql .= ' AND tags.tag IS NULL';
	}
	else
	{
		$sql .= ' AND tags.tag = "' . $tag . '"';
	}
	$sql .= '
			AND sessions.id = '.SESSION_ID.'
			ORDER BY bills.chamber DESC,
			SUBSTRING(bills.number FROM 1 FOR 2) ASC,
			CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
}

# If we're searching by committee.
elseif (!empty($committee))
{

	# Select all bills from the database.
	$sql = 'SELECT bills.number, bills.chamber, bills.catch_line, representatives.name AS patron,
			bills.status AS status_raw, sessions.year, committees.name, committees.chamber,
			bills.date_introduced, bills.status
			FROM bills
			LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
			LEFT JOIN sessions
				ON bills.session_id = sessions.id
			LEFT JOIN committees
				ON bills.last_committee_id = committees.id
			WHERE sessions.id = bills.session_id
			AND committees.shortname = "'.$committee.'"
			AND committees.chamber = "'.$chamber.'"
			AND sessions.year = '.SESSION_YEAR.'
			AND bills.status != "failed" AND bills.status != "passed '.$chamber.'" AND
			bills.status != "passed committee" AND bills.status != "failed committee"
			AND bills.outcome IS NULL
			ORDER BY bills.chamber DESC,
			SUBSTRING(bills.number FROM 1 FOR 2) ASC,
			CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
}

# If we're searching by year.
else
{
	
	# If we're also searching by status.
	if (!empty($status))
	{
		if ($status == 'passed')
		{
			$where_sql = 'AND outcome="passed"';
		}
		elseif ($status == 'failed')
		{
			$where_sql = 'AND outcome="failed"';
		}
	}
	
	# If we're also searching by the presence of tags
	if (!empty($tagless))
	{
		$where_sql = '
			AND 
				(SELECT COUNT(*)
				FROM tags
				WHERE tags.bill_id=bills.id)
			= 0';
	}
	
	# If we're also searching by session ID suffix.
	if (!empty($session_suffix))
	{
		$where_sql = 'AND sessions.suffix="'.$session_suffix.'"';
	}

	# Select all bills from the database.
	$sql = 'SELECT bills.number, bills.chamber, bills.catch_line, bills.status AS status_raw,
			representatives.name AS patron, sessions.year, bills.date_introduced, bills.status
			FROM bills
			LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
			LEFT JOIN sessions
				ON bills.session_id = sessions.id
			WHERE sessions.year = '.$year.' '.(!empty($where_sql) ? $where_sql : '').'
			ORDER BY bills.chamber DESC,
			SUBSTRING(bills.number FROM 1 FOR 2) ASC,
			CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
}

$result = mysql_query($sql);
$num_results = mysql_num_rows($result);
if ($num_results > 0)
{	
	$page_body .= '<p>'.number_format($num_results).' bill'.($num_results > 1 ? 's': '').' found.</p>';
		
	# If this is a listing of bills currently in a given committee.
	if (!empty($committee) && !empty($chamber))
	{
		$page_body .= '
			<table id="'.$committee.'" class="bill-listing sortable">
				<thead>
					<tr>
						<th>#</th>
						<th>Title</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>';
	}
	
	# Loop through the bill results.
	while ($bill = mysql_fetch_array($result))
	{
		
		$bill = array_map('stripslashes', $bill);
		
		# Simplify the status text.
		if (stristr($bill['status'], 'failed') !== FALSE)
		{
			$bill['status'] = 'dead';
		}
	
		# We want to display the house bills, then the senate bills. But we need some way to
		# know when we've crossed that boundary, and that's what we use the $chamber flag for.
		if (!isset($chamber))
		{
			$chamber = $bill['chamber'];
			$page_body .= '
			<div class="tabs">
			<ul>
				<li><a href="#house">House</a></li>
				<li><a href="#senate">Senate</a></li>
			</ul>
			<div id="' . $chamber . '">
				<table id="' . $chamber . '" class="bill-listing sortable">
					<thead>
						<tr>
							<th>#</th>
							<th>Title</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>';
		}
		elseif ($chamber != $bill['chamber'])
		{
			$chamber = $bill['chamber'];
			$page_body .= '</tbody>
				</table>
			</div>
			<div id="'.$chamber.'">
				<table id="'.$chamber.'" class="bill-listing sortable">
					<thead>
						<tr>
							<th>#</th>
							<th>Title</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>';
		}
		$page_body .= '<tr>
						<td><a href="/bill/' . $bill['year'] . '/' . $bill['number'] .
							'/" class="balloon">' . strtoupper($bill['number']) .
							balloon($bill, 'bill').'</a></td>
						<td>' . $bill['catch_line'] . '</td>
						<td>' . $bill['status'] . '</td>
					</tr>';
	}
	$page_body .= '</tbody></table></div></div>';
}

else
{
	$page_body = '<p>No bills have yet been filed for the ' . $year . ' session.</p>';
}

# PAGE SIDEBAR
if (!empty($year))
{
	$page_sidebar = '
	<div class="box" id="options">
		<h3>Options</h3>
		<p>View bills for:</p>
		<ul>
			<li><a href="/bills/2006/">2006</a></li>
			<li><a href="/bills/2007/">2007</a></li>
			<li><a href="/bills/2008/">2008</a>
			<ul>
				<li><a href="/bills/2008/1/">General Session</a></li>
				<li><a href="/bills/2008/3/">Transportation Session</a></li>
			</ul></li>
			<li><a href="/bills/2009/">2009</a>
			<ul>
				<li><a href="/bills/2009/1/">General Session</a></li>
				<li><a href="/bills/2009/2/">Special Session</a></li>
			</ul></li>
			<li><a href="/bills/2010/">2010</a></li>
			<li><a href="/bills/2011/">2011</a>
				<ul>
					<li><a href="/bills/2011/1/">General Session</li>
					<li><a href="/bills/2011/2/">Redistricting Session</li>
				</ul>
			</li>
			<li><a href="/bills/2012/">2012</a>			
				<ul>
					<li><a href="/bills/2012/1/">General Session</li>
					<li><a href="/bills/2012/2/">Budget Session</li>
				</ul>
			</li>
			<li><a href="/bills/2013/">2013</a></li>
			<li><a href="/bills/2014/">2014</a>
				<ul>
					<li><a href="/bills/2014/1/">General Session</li>
					<li><a href="/bills/2014/2/">Budget Session</li>
				</ul>
			</li>
			<li><a href="/bills/2015/">2015</a></li>
			<li><a href="/bills/2016/">2016</a></li>
			<li><a href="/bills/2017/">2017</a></li>
			<li><a href="/bills/2018/">2018</a></li>
		</ul>
		
		<p style="margin-top: 1em;">View bills that:</p>
		<ul>
			<li><a href="/bills/' . $year . '/passed/">passed</a></li>
			<li><a href="/bills/' . $year . '/failed/">failed</a></li>
			<li><a href="/bills/' . $year . '/">all</a></li>
		</ul>
	</div>';

	$page_sidebar .= '
	<div class="box">
		<h3>Explanation</h3>
		<p>These are all of the bills proposed for '.$year;
		
	if (isset($status))
	{
		if ($status == 'passed')
		{
			$page_sidebar .= ' that passed into law.';
		}
		elseif ($status == 'failed')
		{
			$page_sidebar .= ' that failed to become law. They may have simply
			never made it out of committee, they could have been vetoed by the
			governor, or they could have run into trouble anywhere in the long
			path between those two points.';
		}
	}
	else
	{
		if ($year < SESSION_YEAR)
		{
			$page_sidebar .= '. Some of these bills passed,
		becoming a part of Virginia’s laws, but most of them failed.</p>';
		}
		else
		{
			$page_sidebar .= '. Some of these bills will pass, becoming a part of
		Virginia law, but the overwhelming majority will not make it. It’s
		kind of like sea turtle hatchlings: out of dozens and dozens of eggs,
		only a few make it safely past the seagulls to the water, and of those
		just one is likely to grow to adulthood.</p>
		
		<p>That being the world’s only known sea turtle / lawmaking analogy.</p>';
		}
	}
	$page_sidebar .= '
	</div>';

	# Tag Cloud
	$sql = 'SELECT COUNT(*) AS count, tags.tag
			FROM tags
			LEFT JOIN bills
				ON tags.bill_id = bills.id
			LEFT JOIN sessions
				ON sessions.id=bills.session_id
			WHERE sessions.year='.$year;
	if (!empty($session_suffix))
	{
		$sql .= ' AND sessions.suffix="'.$session_suffix.'"';
	}
	$sql .= '
		GROUP BY tags.tag';
	if ($year >= 2007)
	{
		$sql .= ' HAVING count > 5';
	}
	$sql .= ' ORDER BY tags.tag ASC';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		$page_sidebar .= '
	<a href="javascript:openpopup(\'/help/tag-clouds/\')"><img src="/images/help-gray.gif" class="help-icon" alt="?" /></a>
	
	<div class="box">
		<h3>Tag Cloud</h3>
		<div class="tags">';
		$top_tag = 1;
		$top_tag_size = 3;
		while ($tag = mysql_fetch_array($result))
		{
			$tags[] = array_map('stripslashes', $tag);
			if (($tag['count'] > $top_tag) && ($tag['tag'] != 'commendation')) $top_tag = $tag['count'];
		}

		for ($i=0; $i<count($tags); $i++)
		{
			$font_size = $tags[$i]['count'] / $top_tag * $top_tag_size;
			if ($font_size < '.75') $font_size = '.75';
			elseif ($font_size > $top_tag_size) $font_size = $top_tag_size;
			$page_sidebar .= '<span style="font-size: '.$font_size.'em;">
				<a href="/bills/tags/'.urlencode($tags[$i]['tag']).'/">'.$tags[$i]['tag'].'</a>
			</span>';
		}
		$page_sidebar .= '
		</div>
	</div>';
	}
}

if (!empty($tag))
{
	
	# Tag Cloud
	# Show every tag cloud of all tags related to this tag.
	$sql = 'SELECT COUNT(*) AS count, tags2.tag
			FROM tags
			LEFT JOIN tags AS tags2
				ON tags.bill_id=tags2.bill_id
			LEFT JOIN bills
				ON tags2.bill_id = bills.id
			WHERE tags.tag="'.$tag.'" AND tags2.tag != "'.$tag.'"
			AND bills.session_id = '.SESSION_ID.'
			GROUP BY tags2.tag
			ORDER BY tag ASC';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		$page_sidebar .= '
	<a href="javascript:openpopup(\'/help/tag-clouds/\')"><img src="/images/help-gray.gif" class="help-icon" alt="?" /></a>
	
	<div class="box">
		<h3>Related Tag Cloud</h3>
		<div class="tags">';
		while ($tag_data = mysql_fetch_array($result))
		{
			$tags[] = array_map('stripslashes', $tag_data);
		}
		for ($i=0; $i<count($tags); $i++)
		{
			$font_size = round(sqrt($tags[$i]['count']), 2);
			if ($font_size < '.75') $font_size = '.75';
			$page_sidebar .= '<span style="font-size: '.$font_size.'em;">
				<a href="/bills/tags/'.urlencode($tags[$i]['tag']).'/">'.$tags[$i]['tag'].'</a>
			</span>';
		}
		$page_sidebar .= '
		</div>
	</div>';
	}
	

	$page_sidebar .= '
	<div class="box">
		<h3>Subscribe</h3>
		<p><a href="/rss/tag/'.urlencode($tag).'/"><img src="/images/rss-icon.png"
		width="14" height="14" alt="RSS Feed" /></a>
		Keep track of all bills tagged with &ldquo;'.$tag.'&rdquo; &mdash;
		<a href="/rss/tag/'.urlencode($tag).'/">subscribe via RSS</a>.</p>
	</div>';
	
	# Insert the RSS header.		
	$html_head .= '
<link rel="alternate" type="application/rss+xml" title="RSS 0.92" href="/rss/tag/'.urlencode($tag).'/" />';
	
}

if (!empty($committee) && !empty($chamber))
{
	# Get the committee's name and chamber.
	$sql = 'SELECT name, chamber
			FROM committees
			WHERE shortname="'.$committee.'"
			AND chamber="'.$chamber.'"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		$committee = mysql_fetch_array($result);
		$page_title = ucfirst($committee['chamber']).' '.$committee['name'].' Bills';
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
