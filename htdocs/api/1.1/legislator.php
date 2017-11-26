<?php

###
# Create Legislator JSON
# 
# PURPOSE
# Accepts the shortname of a given legislator and spits out a JSON file providing
# the basic specs on that legislator.
# 
# NOTES
# This is not intended to be viewed. It just spits out an JSON file and that's that.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php';
require_once 'functions.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# LOCALIZE VARIABLES
$shortname = @mysql_real_escape_string($_GET['shortname']);
if (isset($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
}

# Select general legislator data from the database.
$sql = 'SELECT representatives.id, representatives.shortname, representatives.name,
		representatives.name_formatted, representatives.place, representatives.chamber,
		representatives.sex, representatives.birthday, representatives.party,
		representatives.url, representatives.email, representatives.address_district,
		representatives.address_richmond, representatives.phone_district,
		representatives.phone_richmond, representatives.date_started, representatives.date_ended,
		representatives.partisanship, representatives.latitude AS longitude,
		representatives.longitude AS latitude, representatives.lis_shortname AS lis_id,
		districts.number AS district, districts.description AS district_description
		FROM representatives
		LEFT JOIN districts
			ON representatives.district_id=districts.id
		WHERE shortname = "'.$shortname.'"';

$result = @mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{
	
	$legislator = @mysql_fetch_array($result, MYSQL_ASSOC);
	$legislator = array_map('stripslashes', $legislator);
	
	# Eliminate any useless data.
	if ($legislator['birthday'] == '0000-00-00')
	{
		unset($legislator['birthday']);
	}
	if ($legislator['date_started'] == '0000-00-00')
	{
		unset($legislator['date_started']);
	}
	if ($legislator['date_ended'] == '0000-00-00')
	{
		unset($legislator['date_ended']);
	}
	if (empty($legislator['phone_district']))
	{
		unset($legislator['phone_district']);
	}
	if (empty($legislator['phone_richmond']))
	{
		unset($legislator['phone_richmond']);
	}
	if (empty($legislator['address_district']))
	{
		unset($legislator['address_district']);
	}
	if (empty($legislator['address_richmond']))
	{
		unset($legislator['address_richmond']);
	}

	# Select the committee data from the database.
	$sql = 'SELECT committees.name, committee_members.position
			FROM committees
			LEFT JOIN committee_members
				ON committees.id = committee_members.committee_id
			WHERE committee_members.representative_id = '.$legislator['id'].'
			AND (date_ended = "0000-00-00" OR date_ended IS NULL)';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		while ($committee = @mysql_fetch_array($result, MYSQL_ASSOC))
		{
			$committee = array_map('stripslashes', $committee);
			if (empty($committee['position']))
			{
				$committee['position'] = 'member';
			}
			$legislator['committees'][] = $committee;
		}
	}
	
	# Select the bill data from the database.
	$sql = 'SELECT bills.number, sessions.year, bills.catch_line AS title, bills.date_introduced,
			bills.outcome
			FROM bills
			LEFT JOIN sessions
				ON bills.session_id=sessions.id
			WHERE bills.chief_patron_id='.$legislator['id'].'
			ORDER BY sessions.year ASC,
			SUBSTRING(bills.number FROM 1 FOR 2) ASC,
			CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		while ($bill = @mysql_fetch_array($result, MYSQL_ASSOC))
		{
			$bill['url'] = 'http://www.richmondsunlight.com/bill/'.$bill['year']
				.'/'.$bill['number'].'/';
			$bill['number'] = strtoupper($bill['number']);
			$legislator['bills'][] = $bill;
		}
	}
}

# We publicly call the shortname the "ID," so swap them out.
$legislator['id'] = $legislator['shortname'];
unset($legislator['shortname']);

# Send an HTTP header defining the content as JSON.
header('Content-type: application/json');

# Send an HTTP header allowing CORS.
header("Access-Control-Allow-Origin: *");

# Send the JSON. If a callback has been specified, prefix the JSON with that callback and wrap the
# JSON in parentheses.
if (isset($callback))
{
	echo $callback.' (';
}
echo json_encode($legislator);
if (isset($callback))
{
	echo ');';
}

?>