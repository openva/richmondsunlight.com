<?php

###
# Create Bill XML
# 
# PURPOSE
# Accepts a year and a bill number and spits out an XML file providing the basic specs on that
# bill.
# 
# NOTES
# This is not intended to be viewed. It just spits out an XML file and that's that.
# 
# TODO
# * Cache the output.
# * Add a listing of identical bills.
# * Add the full status history, with each date and status update as individual items.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once 'includes/settings.inc.php';
require_once 'includes/functions.inc.php';
require_once 'XML/Serializer.php';
include_once('vendor/autoload.php');


# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# LOCALIZE VARIABLES
$year = mysql_escape_string($_REQUEST['year']);
$bill = mysql_escape_string($_REQUEST['bill']);

# Set some options for XML_Serializer.
$serializer_options = array ( 
	'addDecl' => TRUE, 
	'encoding' => 'UTF-8', 
	'indent' => "\t", 
	'rootName' => 'bill', 
);

# Select the bill data from the database.
$sql = 'SELECT bills.number, bills.catch_line AS title, bills.summary, bills.full_text,
		bills.current_chamber, bills.status, bills.date_introduced, representatives.shortname,
		representatives.name, representatives.party
		FROM bills
		LEFT JOIN representatives ON bills.chief_patron_id=representatives.id
		LEFT JOIN districts ON representatives.district_id=districts.id
		LEFT JOIN sessions ON bills.session_id=sessions.id
		WHERE bills.number = "'.$bill.'" AND sessions.year='.$year;
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	die('Richmond Sunlight has no record of bill '.$bill.' in '.$year.'.');
}
# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
$bill = mysql_fetch_array($result, MYSQL_ASSOC);
$bill = array_map('stripslashes', $bill);

# Remove the HTML and the newlines from the bill summary.
$bill['summary'] = strip_tags($bill['summary']);
$bill['summary'] = str_replace("\n", ' ', $bill['summary']);

# Assign the patron data to a subelement.
$bill['patron']['name'] = $bill['name'];
$bill['patron']['id'] = $bill['shortname'];
$bill['patron']['party'] = $bill['party'];
unset($bill['name']);
unset($bill['shortname']);
unset($bill['party']);

// somehow, drop all of the integer-indexed duplicate array

# Create a new instance of the serializer, passing to it the options previously set.
$Serializer = &new XML_Serializer($serializer_options);

# Serialize the bill array data as XML, saving the success as a $status flag.
$status = $Serializer->serialize($bill);

# Check the result of the XML serialization to make sure it worked.
// Returning the raw error is pretty heinous. Maybe the error could be embedded as
// XML, and the API documentation could instruct clients to look for errors?
if (PEAR::isError($status))
{
	die($status->getMessage());
}

# Send an HTTP header defining the content as XML.
header('Content-type: text/xml');

# Send the XML.
echo $Serializer->getSerializedData(); 

?>