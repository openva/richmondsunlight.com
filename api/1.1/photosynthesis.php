<?php

###
# Create Photosynthesis JSON
# 
# PURPOSE
# Accepts a Photosynthesis portfolio hash, and responds with a listing of bills contained within
# that portfolio, along with any associated comments.
# 
# NOTES
# This is not intended to be viewed. It just spits out a JSON file and that's that.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php';
require_once 'functions.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# LOCALIZE VARIABLES
$hash = mysql_escape_string(urldecode($_REQUEST['hash']));
if (isset($_REQUEST['callback']) && !empty($_REQUEST['callback']))
{
	$callback = $_REQUEST['callback'];
}

# Select the bill data from the database.
$sql = 'SELECT bills.number, sessions.year, dashboard_bills.notes
		FROM dashboard_portfolios
		LEFT JOIN dashboard_user_data
			ON dashboard_portfolios.user_id=dashboard_user_data.user_id
		LEFT JOIN dashboard_bills
			ON dashboard_portfolios.id=dashboard_bills.portfolio_id
		LEFT JOIN bills
			ON dashboard_bills.bill_id=bills.id
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE dashboard_portfolios.hash="'.$hash.'"
		AND bills.session_id='.SESSION_ID.'
		ORDER BY bills.chamber DESC,
		SUBSTRING(bills.number FROM 1 FOR 2) ASC,
		CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	header("Status: 404 Not Found");
	$message = array('error' =>
		array('message' => 'No Bills Found',
			'details' => 'No bills were found in portfolio '.$hash.'.'));
	echo json_encode($message);
	exit;
}

# Build up a listing of all bills.
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
while ($bill = mysql_fetch_array($result, MYSQL_ASSOC))
{
	$bill['url'] = 'http://www.richmondsunlight.com/bill/'.$bill['year'].'/'.$bill['number'].'/';
	$bill['number'] = strtoupper($bill['number']);
	$bills[] = array_map('stripslashes', $bill);
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