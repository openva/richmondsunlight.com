<?php

###
# Mirror the "Current Item" Functionality
# 
# PURPOSE
# Periodically copies the content of the legislature's page where they indicate the current status
# of each chamber, updated every minute or so.
# 
# NOTES
# This should do a couple of things differently. For instance, when the legislature is recessed
# for the day, it should at a minimum not record that fact. Better still, it should stop querying
# for the rest of the day. (This might be accomplished with a query to chamber_status to see if
# the last update for either chamber indicates that it had recessed.) Also, when the standard page
# is being displayed (LIS link at the top, green box saying "[Chamber] Calendar," etc.), then there
# is no need to save all of that HTML. Just the status text. And overall, there is no need to save
# all of the HTML, but only the body content.
#
###

error_reporting(E_ALL);
ini_set('display_errors', 1);
	
# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# Only bother if the chamber is in session.
if (IN_SESSION != 'Y')
{
	die();
}

# If it's between 10am and 5pm.
if ( (date('G') >= 10) && (date('G') <= 16) )
{
	$house = get_content('http://leg5.state.va.us/currentitem/house.htm');
	$senate = get_content('http://leg5.state.va.us/currentitem/senate.htm');
	
	if (!empty($house))
	{
		$house = trim($house);
		$sql = 'INSERT INTO chamber_status
				SET chamber="house", session_id='.SESSION_ID.',
				date=now(), text = "'.addslashes($house).'"';
		mysql_query($sql);
	}
	
	if (!empty($senate))
	{
		$house = trim($senate);
		$sql = 'INSERT INTO chamber_status
				SET chamber="senate", session_id='.SESSION_ID.',
				date=now(), text = "'.addslashes($senate).'"';
		mysql_query($sql);
	}
}

?>
