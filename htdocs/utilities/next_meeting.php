#!/usr/bin/php

<?php

/*
 * 
 */

/*
 * Includes
 */
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

/*
 * Connect to the database.
 */
$db = connect_to_db();

/*
 * Require a "-c" flag indicating the chamber.
 */
$options = getopt('c:');

/*
 *
 */
if (!isset($options['c']))
{

	echo "Error: Chamber must be specified with -c flag (e.g., “-c senate”)\n";
	exit(1);

}

/*
 * Make sure that a valid chamber has been specified.
 */
$chamber = $options['c'];
if ( ($chamber != 'senate') && ($chamber != 'house') )
{
	exit(1);
}

/*
 * See how long it will be, in seconds, until the chamber convenes.
 */
$sql = 'SELECT TIMESTAMPDIFF(SECOND, TIMESTAMP(meetings.date, meetings.time), NOW()) AS time
		FROM meetings
		WHERE description = "' . ucfirst($chamber) . ' Convenes"
		HAVING time < 0
		ORDER BY time DESC
		LIMIT 1';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	$meeting = mysql_fetch_object($result);
	exit((string) abs($meeting->time));
}
else
{
	exit(1);
}
