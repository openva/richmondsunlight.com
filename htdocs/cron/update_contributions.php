<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once '../includes/settings.inc.php';
require_once '../includes/functions.inc.php';

# Set a time limit of 4 minutes for this script to run.
set_time_limit(240);

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();


# Get a list of the IDs and the SBE IDs for every legislator who is currently in office.
$sql = 'SELECT id, name, sbe_id
		FROM representatives
		WHERE (date_ended IS NULL OR date_ended > now()) AND sbe_id IS NOT NULL';
$result = mysql_query($sql);

# Iterate through each legislator, getting the data from the API and saving it for each one.
while ($legislator = mysql_fetch_array($result))
{
	
	# Create the URL for this committee query.
	$url = 'http://openva.com/campaign-finance/committees/' . $legislator['sbe_id'] . '.json';
	
	# Get the JSON from the remote URL.
	$json = get_content($url);
	
	# If that failed, go to the next legislator.
	if ($json === FALSE)
	{
		continue;
	}
	
	$contributions = json_decode($json);
	
	# Then get the list of individual contributions.
	$url = 'http://openva.com/campaign-finance/contributions/' . $legislator['sbe_id'] . '.json';
	
	# Get the JSON from the remote URL.
	$json = get_content($url);
	
	if ($json !== FALSE)
	{
		$contributions->List = json_decode($json);
	}
	
	# Insert it into the database.
	$sql = 'UPDATE representatives
			SET contributions="' . addslashes(serialize($contributions)) . '"
			WHERE id=' .$legislator['id'];
	mysql_query($sql);
	if (mysql_affected_rows() === 1)
	{
		echo '<p>Legislator ' . $legislator['name'] . ' updated.</p>';
	}
	else
	{
		echo '<p>Legislator ' . $legislator['name'] . ' unaffected.</p>';
	}
	
}

?>
