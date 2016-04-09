<?php

###
# Create Bill Listing JSON
# 
# PURPOSE
# Accepts a year and spits out a JSON file providing a list of the bills introduced in that year.
# 
# NOTES
# This is not intended to be viewed. It just spits out an JSON file and that's that.
# 
# TODO
# * Cache the output.
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
if (isset($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
}

# Select the bill data from the database.
$sql = 'SELECT bills.number, bills.chamber, bills.date_introduced, bills.status, bills.outcome,
		bills.catch_line AS title, representatives.name_formatted AS patron,
		representatives.shortname AS patron_id
		FROM bills
		LEFT JOIN representatives
			ON bills.chief_patron_id=representatives.id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE sessions.year='.$year.'
		ORDER BY bills.chamber DESC,
		SUBSTRING(bills.number FROM 1 FOR 2) ASC,
		CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	// send this as a JSON-formatted error!
	die('Richmond Sunlight has no record of bills for '.$year.'.');
}

$bills = array();

# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
while ($bill = mysql_fetch_array($result, MYSQL_ASSOC))
{
	$bill = array_map('stripslashes', $bill);

	# Assign the patron data to a subelement.
	$bill['patron']['name'] = $bill['patron'];
	$bill['patron']['id'] = $bill['patron_id'];
	
	# Eliminate the fields we no longer need.
	unset($bill['patron']);
	unset($bill['patron_id']);
	
	$bills[] = $bill;
}

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
echo json_encode($bills);
if (isset($callback))
{
	echo ');';
}

?>