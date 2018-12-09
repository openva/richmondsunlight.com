<?php

###
# Create Bill JSON
#
# PURPOSE
# Accepts a section of code, and responds with a listing of bills that addressed that section.
#
# NOTES
# This is not intended to be viewed. It just spits out a JSON file and that's that.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once 'includes/settings.inc.php';
require_once 'includes/functions.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# LOCALIZE VARIABLES
$section = mysqli_escape_string($db, urldecode($_REQUEST['section']));
if (isset($_REQUEST['callback']) && !empty($_REQUEST['callback']))
{
    $callback = $_REQUEST['callback'];
}

# Select the bill data from the database.
$sql = 'SELECT sessions.year, bills.number, bills.catch_line
		FROM bills
		LEFT JOIN bills_section_numbers
			ON bills.id = bills_section_numbers.bill_id
		LEFT JOIN sessions
			ON bills.session_id = sessions.id
		WHERE bills_section_numbers.section_number =  "' . $section . '"
		ORDER BY year ASC';
$result = mysqli_query($db, $sql);
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
$bill = mysqli_fetch_array($result, MYSQL_ASSOC);

# Build up a listing of all bills.
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
while ($bill = mysqli_fetch_array($result, MYSQL_ASSOC))
{
    $bill['url'] = 'http://www.richmondsunlight.com/bill/' . $bill['year'] . '/' . $bill['number'] . '/';
    $bill['number'] = mb_strtoupper($bill['number']);
    $bills[] = array_map('stripslashes', $bill);
}

# Send an HTTP header defining the content as JSON.
header('Content-type: application/json');

# Send the JSON. If a callback has been specified, prefix the JSON with that callback and wrap the
# JSON in parentheses.
if (isset($callback))
{
    echo $callback . ' (';
}
echo json_encode($bills);
if (isset($callback))
{
    echo ');';
}
