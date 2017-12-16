<?php

###
# Tag Listing XML
# 
# PURPOSE
# Accepts a year and displays a listing of every tag applied to bills during that year, provided as
# XML.
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
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# LOCALIZE VARIABLES
$year = mysql_escape_string($_REQUEST['year']);

# Set some options for XML_Serializer.
$serializer_options = array ( 
	'addDecl' => TRUE, 
	'encoding' => 'ISO-8859-1', 
	'indent' => "\t", 
	'rootName' => 'tags',
	'defaultTagName' => 'tag',
);

# Select all tags from the database.
$sql = 'SELECT tag, COUNT(*) AS count
		FROM tags
		LEFT JOIN bills ON tags.bill_id=bills.id
		LEFT JOIN sessions ON bills.session_id=sessions.id
		WHERE sessions.year='.$year.'
		GROUP BY tag
		ORDER BY tag ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	die('Richmond Sunlight has no record of any tags for '.$year.'.');
}

# Create the array to be populated below.
$tags = array();

# The MYSQL_ASSOC variable indicates that we want just the associated array, not both associated
# and indexed arrays.
while ($tag = mysql_fetch_array($result, MYSQL_ASSOC))
{
	$tag = array_map('stripslashes', $tag);
	
	# Assign the bill to the element that contains all of them.
	$tags[] = $tag;
}

// somehow, drop all of the integer-indexed duplicate array

# Create a new instance of the serializer, passing to it the options previously set.
$Serializer = &new XML_Serializer($serializer_options);

# Serialize the tag array data as XML, saving the success as a $status flag.
$status = $Serializer->serialize($tags);

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