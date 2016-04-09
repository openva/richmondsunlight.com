<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# Connect to the database via PDO.
$db = connect_to_db('pdo');

/*
 * Retrieve a list of all active legislators' names and URLs.
 */
$chambers = array('house', 'senate');
foreach ($chambers as $chamber)
{
	echo '<h1>' . $chamber . '</h1>';
	$sql = 'SELECT shortname AS url, name_formatted AS name_formatted
			FROM `representatives`
			WHERE date_ended IS NULL AND chamber="' . $chamber . '"
			ORDER BY name ASC';

	$stmt = $db->prepare($sql);
	$stmt->execute();
	$legislators = $stmt->fetchAll(PDO::FETCH_OBJ);

	foreach ($legislators as $legislator)
	{
		echo '<li><a href="/legislator/' . $legislator->url . '/">' . $legislator->name_formatted
			. '</a></li>' . "\n";
	}

}

