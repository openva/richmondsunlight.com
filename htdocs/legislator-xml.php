<?php

	###
	# Create Legislator XML
	#
	# PURPOSE
	# Accepts the shortname of a given legislator and spits out an XML file providing
	# the basic specs on that legislator.
	#
	# NOTES
	# This is not intended to be viewed. It just spits out an XML file and that's that.
	#
	# TODO
	# None.
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

	# LOCALIZE VARIABLES
	$shortname = mysql_real_escape_string($_GET['shortname']);

	# Select the vote data from the database.
	$sql = 'SELECT representatives.id, representatives.shortname, representatives.name,
			representatives.chamber, representatives.sex, representatives.birthday,
			districts.number AS district, representatives.party,
			representatives.cash_on_hand AS money,
				(SELECT COUNT(*)
				FROM bills
				WHERE chief_patron_id = representatives.id AND session_id = '.SESSION_ID.')
				AS bill_count
			FROM representatives LEFT JOIN districts ON representatives.district_id=districts.id
			WHERE shortname = "'.$shortname.'"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{

		// Send the headers to have the data downloaded as XML.

		$legislator = mysql_fetch_array($result);
		$legislator = array_map('stripslashes', $legislator);
		echo '<legislator>
	<name>'.$legislator['name'].'</name>
	<id>'.$legislator['shortname'].'</id>
	<district>'.$legislator['district'].'</district>
	<chamber>'.$legislator['chamber'].'</chamber>
	<party>'.$legislator['party'].'</party>
	<bill_count>'.$legislator['bill_count'].'</bill_count>
	<sex>'.$legislator['sex'].'</sex>
	<money>'.$legislator['money'].'</money>';
		if ($legislator['birthday'] != '0000-00-00') echo '
	<birthday>'.$legislator['birthday'].'</birthday>';

		# Select the vote data from the database.
		$sql = 'SELECT COUNT(*) AS count, tags.tag AS term
				FROM tags
				LEFT JOIN bills
				ON tags.bill_id = bills.id
				LEFT JOIN representatives
				ON bills.chief_patron_id = representatives.id
				WHERE representatives.id = '.$legislator['id'].'
				GROUP BY tags.tag
				ORDER BY tags.tag ASC';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
				echo '
	<tags>';
			while ($tags = mysql_fetch_array($result))
			{
				$tags = array_map('stripslashes', $tags);
				echo '
		<tag>
			<term>'.$tags['term'].'</term>
			<count>'.$tags['count'].'</count>
		</tag>';
			}
				echo '
	</tags>';
		}
		# Select the committee data from the database.
		$sql = 'SELECT committees.name, committee_members.position
				FROM committees
				LEFT JOIN committee_members
				ON committees.id = committee_members.committee_id
				WHERE committee_members.representative_id = '.$legislator['id'].'
				AND (date_ended = "0000-00-00" OR date_ended IS NULL)';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			echo '
	<committees>';
			while ($committee = mysql_fetch_array($result))
			{
				$committee = array_map('stripslashes', $committee);
				if (empty($committee['position']))
				{
					$committee['position'] = 'member';
				}
				echo '
		<committee>
			<name>'.$committee['name'].'</name>
			<position>'.$committee['position'].'</position>
		</committee>';
			}
			echo '
	</committees>';
		}

		# Select the bill data from the database.
		$sql = 'SELECT bills.number, bills.catch_line AS title, bills.date_introduced, bills.status
				FROM bills
				LEFT JOIN sessions ON bills.session_id=sessions.id
				WHERE sessions.year='.SESSION_YEAR.' AND bills.chief_patron_id='.$legislator['id'].'
				ORDER BY bills.chamber DESC,
				SUBSTRING(bills.number FROM 1 FOR 2) ASC,
				CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			echo '
	<bills>';
			while ($bill = mysql_fetch_array($result))
			{
				echo '
		<bill>
			<number>'.strtoupper($bill['number']).'</number>
			<title>'.$bill['title'].'</title>
			<date_introduced>'.$bill['date_introduced'].'</date_introduced>
			<status>'.$bill['status'].'</status>
		</bill>';
			}
			echo '
	</bills>';
		}
echo '
</legislator>';
	}

?>
