<?php

###
# Create Bill JSON
# 
# PURPOSE
# Accepts a year and a bill number and spits out a JSON file providing the basic specs on that
# bill.
# 
# NOTES
# This is not intended to be viewed. It just spits out an JSON file and that's that.
# 
# TODO
# * Cache the output.
# * Add a listing of identical bills.
# * Add the full status history, with each date and status update as individual items.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php';
require_once 'functions.inc.php';


# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific page.
@connect_to_db();

# LOCALIZE VARIABLES
$year = mysql_escape_string($_REQUEST['year']);
$bill = mysql_escape_string($_REQUEST['bill']);
if (isset($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
}

# Select the bill data from the database.
$sql = 'SELECT bills.id, bills.number, bills.current_chamber, bills.status, bills.date_introduced,
		bills.outcome, bills.catch_line AS title, bills.summary, bills.full_text AS text,
		representatives.shortname, representatives.name_formatted AS name
		FROM bills
		LEFT JOIN representatives
			ON bills.chief_patron_id=representatives.id
		LEFT JOIN districts
			ON representatives.district_id=districts.id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE bills.number = "'.$bill.'" AND sessions.year='.$year;
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	json_error('Richmond Sunlight has no record of bill ' . strtoupper($bill) . ' in ' . $year . '.');
	exit();
}
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
$bill = mysql_fetch_array($result, MYSQL_ASSOC);
$bill = array_map('stripslashes', $bill);

# Select tags from the database.
$sql = 'SELECT tag
		FROM tags
		WHERE bill_id=' . $bill['id'] . '
		ORDER BY tag ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	while ($tag = mysql_fetch_array($result, MYSQL_ASSOC))
	{
		$bill['tags'][] = $tag;
	}
}

# Remove the HTML and the newlines from the bill summary.
$bill['summary'] = strip_tags($bill['summary']);
$bill['summary'] = str_replace("\n", ' ', $bill['summary']);

# Remove the newlines from the bill text.
$bill['text'] = str_replace("\r", '', $bill['text']);

# Assign the patron data to a subelement.
$bill['patron']['name'] = $bill['name'];
$bill['patron']['id'] = $bill['shortname'];

# Eliminate the fields we no longer need.
unset($bill['name']);
unset($bill['shortname']);
unset($bill['party']);
unset($bill['id']);

# Send an HTTP header defining the content as JSON.
header('Content-type: application/json');
header("Access-Control-Allow-Origin: *");

# Send the JSON. If a callback has been specified, prefix the JSON with that callback and wrap the
# JSON in parentheses.
if (isset($callback))
{
	echo $callback.' (';
}
echo json_encode($bill);
if (isset($callback))
{
	echo ');';
}

?>