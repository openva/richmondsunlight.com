<?php

###
# Bill Listing XML
# 
# PURPOSE
# Accepts a year and displays a listing of every bill introduced in that year, provided as XML.
# 
# NOTES
# This is not intended to be viewed. It just spits out an XML file and that's that.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once 'includes/settings.inc.php';
require_once 'includes/functions.inc.php';
require_once 'XML/Serializer.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# LOCALIZE VARIABLES
$year = mysql_escape_string($_REQUEST['year']);

# Set some options for XML_Serializer.
$serializer_options = array ( 
	'addDecl' => TRUE, 
	'encoding' => 'ISO-8859-1', 
	'indent' => "\t", 
	'rootName' => 'bills',
	'defaultTagName' => 'bill',
);

# Select all bills from the database.
$sql = 'SELECT bills.number, bills.catch_line AS title, representatives.name AS patron_name,
		representatives.shortname AS patron_shortname, representatives.party AS patron_party,
		bills.date_introduced, bills.status
		FROM bills LEFT JOIN representatives ON bills.chief_patron_id = representatives.id
		LEFT JOIN sessions ON bills.session_id=sessions.id
		WHERE sessions.year='.$year.'
		ORDER BY bills.chamber DESC,
		SUBSTRING(bills.number FROM 1 FOR 2) ASC,
		CAST(LPAD(SUBSTRING(bills.number FROM 3), 4, "0") AS unsigned) ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	die('Richmond Sunlight has no record of any bills for '.$year.'.');
}

# Create the array to be populated below.
$bills = array();

# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
while ($bill = mysql_fetch_array($result, MYSQL_ASSOC))
{
	$bill = array_map('stripslashes', $bill);

	# Assign the patron data to a subelement.
	$bill['patron']['name'] = $bill['patron_name'];
	$bill['patron']['id'] = $bill['patron_shortname'];
	$bill['patron']['party'] = $bill['patron_party'];
	unset($bill['patron_name']);
	unset($bill['patron_shortname']);
	unset($bill['patron_party']);
	
	# Assign the bill to the element that contains all of them.
	$bills[] = $bill;
}

// somehow, drop the integer-indexed duplicate array

# Create a new instance of the serializer, passing to it the options previously set.
$Serializer = &new XML_Serializer($serializer_options);

# Serialize the bill array data as XML, saving the success as a $status flag.
$status = $Serializer->serialize($bills);

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