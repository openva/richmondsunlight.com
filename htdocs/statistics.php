<?php

	###
	# Statistics
	# 
	# PURPOSE
	# Lists misc. statistics about bills.
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
	include_once('includes/functions.inc.php');
	include_once('includes/settings.inc.php');
	include_once('includes/charts.php');
	include_once('vendor/autoload.php');
	
	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.
	$database = new Database;
$database->connect_old();
	
	# PAGE METADATA
	$page_title = 'Statistics';
	$site_section = 'statistics';
	
	# PAGE CONTENT
	
	$page_body = '<h2>Daily Activity</h2>
	'.InsertChart('/images/charts.swf', '/images/charts_library', '/charts/statistics.php?id=daily-activity', 400, 250);
	
	$page_body .= '<h2>Cumulative Bills Introduced</h2>
	'.InsertChart('/images/charts.swf', '/images/charts_library', '/charts/statistics.php?id=cum-introduced', 400, 250);

	$page_body .= '<h2>Top 10 Bill Filers</h2>
	'.InsertChart('/images/charts.swf', '/images/charts_library', '/charts/statistics.php?id=top-filers', 400, 250);

	$page_body .= '<h2>Top 10 Most-Viewed Bills</h2>
	'.InsertChart('/images/charts.swf', '/images/charts_library', '/charts/statistics.php?id=most-viewed', 400, 250);
	
	
	# SIDEBAR
	
	# Select the total number of bills introduced in each chamber.
	$sql = 'SELECT chamber, COUNT(*) AS count
			FROM bills
			WHERE session_id='.SESSION_ID.'
			GROUP BY chamber';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		$page_sidebar .= '
			<div class="box">
				<h3>By Chamber</h3>';
		while ($chamber = @mysql_fetch_array($result))
		{
			if ($chamber['chamber'] == 'house')
			{
				$house['count'] = number_format($chamber['count']);
				$house['avg'] = round(($chamber['count'] / 100), 1);
			}
			elseif ($chamber['chamber'] == 'senate')
			{
				$senate['count'] = number_format($chamber['count']);
				$senate['avg'] = round(($chamber['count'] / 40), 1);
			}
		}
			
		$page_sidebar .= '
				<strong>Senate</strong>
				<ul>
					<li>'.$senate['count'].' total bills</li>
					<li>'.$senate['avg'].' bills per legislator</li>
				</ul>
				<strong>House</strong>
				<ul>
					<li>'.$house['count'].' total bills</li>
					<li>'.$house['avg'].' bills per legislator</li>
				</ul>';
		$page_sidebar .= '
			</div>';
	}
	
	
	# Select the total number of bills introduced in each chamber.
	$sql = 'SELECT representatives.party, COUNT(*) AS count,
			(
				SELECT COUNT(*)
				FROM representatives
				WHERE party="D"
				AND date_ended IS NULL
			) AS democrats_count,
			(
				SELECT COUNT(*)
				FROM representatives
				WHERE party="R"
				AND date_ended IS NULL
			) AS republicans_count
			FROM bills
			LEFT JOIN representatives ON bills.chief_patron_id=representatives.id
			WHERE bills.session_id='.SESSION_ID.'
			GROUP BY party';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		$page_sidebar .= '
			<div class="box">
				<h3>By Party</h3>';
		while ($party = @mysql_fetch_array($result))
		{
			if ($party['party'] == 'R')
			{
				$republican['count'] = number_format($party['count']);
				$republican['avg'] = round(($party['count'] / $party['republicans_count']), 1);
			}
			elseif ($party['party'] == 'D')
			{
				$democratic['count'] = number_format($party['count']);
				$democratic['avg'] = round(($party['count'] / $party['democrats_count']), 1);
			}
		}
			
		$page_sidebar .= '
				<strong>Republican</strong>
				<ul>
					<li>'.$republican['count'].' total bills</li>
					<li>'.$republican['avg'].' bills per legislator</li>
				</ul>
				<strong>Democratic</strong>
				<ul>
					<li>'.$democratic['count'].' total bills</li>
					<li>'.$democratic['avg'].' bills per legislator</li>
				</ul>';
		$page_sidebar .= '
			</div>';
	}
	
	# Republican Tag Cloud
	$sql = 'SELECT COUNT(*) AS count, tags.tag
			FROM tags
			LEFT JOIN bills
			ON tags.bill_id = bills.id
			LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
			WHERE representatives.party = "R" AND bills.session_id = '.SESSION_ID.'
			GROUP BY tags.tag
			HAVING count > 5
			ORDER BY tags.tag ASC';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		$page_sidebar .= '
		<a href="javascript:openpopup(\'/help/tag-clouds/\')" title="Help"><img src="/images/help-beige.gif" class="help-icon" alt="?" /></a>
		
		<div class="box">
			<h3>Republican Tag Cloud</h3>
			<div class="tags">';
		while ($tag = @mysql_fetch_array($result))
		{
			$tags[] = array_map('stripslashes', $tag);
		}
		for ($i=0; $i<count($tags); $i++)
		{
			$font_size = round((log($tags[$i]['count']) / 2), 2);
			if ($font_size < '.75') $font_size = '.75';
			$page_sidebar .= '<span style="font-size: '.$font_size.'em;">
					<a href="/bills/tags/'.urlencode($tags[$i]['tag']).'/">'.$tags[$i]['tag'].'</a>
				</span>';
		}
		$page_sidebar .= '
			</div>
		</div>';
		unset($tags);
	}
	
	# Democratic Tag Cloud
	$sql = 'SELECT COUNT(*) AS count, tags.tag
			FROM tags
			LEFT JOIN bills
			ON tags.bill_id = bills.id
			LEFT JOIN representatives
			ON bills.chief_patron_id = representatives.id
			WHERE representatives.party = "D" AND bills.session_id = '.SESSION_ID.'
			GROUP BY tags.tag
			HAVING count > 3
			ORDER BY tags.tag ASC';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		$page_sidebar .= '
		<a href="javascript:openpopup(\'/help/tag-clouds/\')" title="Help"><img src="/images/help-beige.gif" class="help-icon" alt="?" /></a>
		
		<div class="box">
			<h3>Democratic Tag Cloud</h3>
			<div class="tags">';
		while ($tag = @mysql_fetch_array($result))
		{
			$tags[] = array_map('stripslashes', $tag);
		}
		for ($i=0; $i<count($tags); $i++)
		{
			$font_size = round((log($tags[$i]['count']) / 2), 2);
			if ($font_size < '.75') $font_size = '.75';
			$page_sidebar .= '<span style="font-size: '.$font_size.'em;">
					<a href="/bills/tags/'.urlencode($tags[$i]['tag']).'/">'.$tags[$i]['tag'].'</a>
				</span>';
		}
		$page_sidebar .= '
			</div>
		</div>';
	}
	
	# OUTPUT THE PAGE
	display_page('page_title='.$page_title.'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
		'&site_section='.urlencode($site_section));

?>