<?php

///// TO DO
/*
 * make it part of the update-db.php infrastructure
 *	remove @connect_to_db();
 * disable error reporting etc.
 * there's no need for this to refresh all years of data -- only have it update the present year
 * set up a cron task for this, maybe weekly
 * this isn't OVERWRITING, it's APPENDING
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
include_once('../includes/functions.inc.php');
include_once('../includes/settings.inc.php');

@connect_to_db();

/*
 * Define the URL where the JSON files are found.
 */
define('REMOTE_URL', 'http://openva.com/lobbyists/');

/*
 * Create an array of years, beginning with 2007, until the present year.
 */
$years = range(2007, date('Y'));

/*
 * Iterate through each year and retrieve the JSON file for that year.
 */
foreach ($years as $year)
{
	
	/*
	 * Get the JSON file.
	 */
	$filings = get_content(REMOTE_URL . 'years/' . $year . '.json');
	if ($filings === FALSE)
	{
		die('Could not retrieve the filings for ' . $year);
	}
	
	/*
	 * Turn the JSON into an object.
	 */
	$filings = json_decode($filings);
	if ($filings === FALSE)
	{
		die('The filings for ' . $year . ' are comprised of invalid JSON.');
	}
	
	/*
	 * Iterate through the array and get each filing, one at a time.
	 */
	foreach ($filings as $filing)
	{
		
		$lobbyist = get_content(REMOTE_URL . 'lobbyists/' . $filing->id . '.json');
		if ($lobbyist === FALSE)
		{
			echo 'Could not retrieve the lobbyist record for ' . $filing->name . ' (' . $filing->id
				. ').';
		}
		
		$lobbyist = json_decode($lobbyist);
		if ($lobbist === FALSE)
		{
			echo 'The lobbyist record for ' . $filing->name . ' (' . $filing->id . ') is comprised
				of invalid JSON.';
		}
		
		/*
		 * Concatenate the address data into a single string.
		 */
		$tmp = $lobbyist->address->street_1;
		if (isset($lobbyist->address->street_2))
		{
			$tmp .= ' ' . $lobbyist->address->street_2;
		}
		$tmp .= ', ' . $lobbyist->address->city . ', ' . $lobbyist->address->state . ' '
			. $lobbyist->address->zip_code;
		$lobbyist->address = $tmp;
		unset($zip);
		
		/*
		 * Clean up this data to go into the database.
		 */
		foreach ($lobbyist as &$tmp)
		{
			$tmp = addslashes($tmp);
		}
		
		/*
		 * Assemble the SQL to insert the lobbyist's record.
		 */
		$sql = 'REPLACE INTO lobbyists
				SET name = "' . $lobbyist->name . '",
				sc_id = "' . $lobbyist->id . '",
				id_hash = "' . $lobbyist->hash . '",
				principal = "' . $lobbyist->principal . '",
				address = "' . $lobbyist->address . '",
				phone = "' . $lobbyist->phone_number . '",
				year = "' . $year . '",
				statement = "' . $lobbyist->statement . '",
				date_registered = "' . $lobbyist->registered . '",
				date_created = now()';
				
		if (isset($lobbyist->organization))
		{
			$sql .= 'organization = "' . $lobbyist->organization . '"';
		}
		
		echo '.';
		
		/*
		 * Insert the record.
		 */
		$result = mysql_query($sql);
		
		if ($result === FALSE)
		{
			echo '<p><strong>Query failed:</strong> ' . $sql . '</p>';
		}
		
	}
	
	
}
