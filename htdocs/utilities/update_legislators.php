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
 * Retrieve a list of all active legislators' names and IDs.
 */
$sql = 'SELECT name, chamber, lis_id
		FROM representatives
		WHERE date_ended IS NULL OR date_ended > now()
		ORDER BY chamber ASC';
$stmt = $db->prepare($sql);
$stmt->execute();
$known_legislators = $stmt->fetchAll(PDO::FETCH_OBJ);
foreach ($known_legislators as &$known_legislator)
{
	if ( ($known_legislator->lis_id[0] != 'S') && ($known_legislator->lis_id[0] != 'H') )
	{
		if ($known_legislator->chamber == 'senate')
		{
			$known_legislator->lis_id = 'S' . $known_legislator->lis_id;
		}
		elseif ($known_legislator->chamber == 'house')
		{
			$known_legislator->lis_id = 'H' . str_pad($known_legislator->lis_id, 4, '0', STR_PAD_LEFT);
		}
	}
}

echo '<p>Loaded ' . count($known_legislators) . ' from local database.</p>';

/*
 * Get senators. Their Senate ID (e.g., "S100") is the key, their name is the value.
 */
$html = get_content('http://apps.senate.virginia.gov/Senator/index.php');
preg_match_all('/id=S([0-9]{2,3})(?:.*)<u>(.+)<\/u>/', $html, $senators);
$tmp = array();
$i=0;
foreach ($senators[1] as $senator)
{
	$tmp['S'.$senator] = $senators[2][$i];
	$i++;
}
$senators = $tmp;
unset($tmp);

echo '<p>Retrieved ' . count($senators) . ' senators from senate.virginia.gov.</p>';

/*
 * Get delegates. Their House ID (e.g., "H0200") is the key, their name is the value.
 */
$html = get_content('http://virginiageneralassembly.gov/house/members/members.php');
preg_match_all('/id=\'member\[H([0-9]+)\]\'><td width="190px"><a class="bioOpener" href="#">(.*?)<\/a>/m', $html, $delegates);
$tmp = array();
$i=0;
foreach ($delegates[1] as $delegate)
{
	$tmp['H'.$delegate] = $delegates[2][$i];
	$i++;
}
$delegates = $tmp;
unset($tmp);

echo '<p>Retrieved ' . count($delegates) . ' delegates from virginiageneralassembly.gov.</p>';

/*
 * First see if we have records of any representatives that are not currently in office.
 */
foreach ($known_legislators as $known_legislator)
{
	
	$id = $known_legislator->lis_id;
	
	/*
	 * Check senators.
	 */
	if ($known_legislator->chamber == 'senate')
	{
		if (!isset($senators[$id]))
		{
			echo '<li>Remove Sen. ' . $known_legislator->name . '</li>';
		}
	}
	
	/*
	 * Check delegates.
	 */
	elseif ($known_legislator->chamber == 'house')
	{
		if (!isset($delegates[$id]))
		{
			echo '<li>Remove Del. ' . $known_legislator->name . '</li>';
		}
	}
	
}

/*
 * Second, see there are any delegates or senators who are not in our records.
 */
foreach ($senators as $lis_id => $name)
{
	
	$match = FALSE;

	foreach ($known_legislators as $known_legislator)
	{
		
		if ($known_legislator->lis_id == $lis_id)
		{
			$match = TRUE;
			continue(2);
		}
		
	}
	
	if ($match == FALSE)
	{
		echo '<li>Add <a href="http://apps.senate.virginia.gov/Senator/memberpage.php?id='
			. $lis_id . '">' . $name . '</a></li>';
	}

}

foreach ($delegates as $lis_id => $name)
{
	
	$match = FALSE;

	foreach ($known_legislators as $known_legislator)
	{
		
		if ($known_legislator->lis_id == $lis_id)
		{
			$match = TRUE;
			continue(2);
		}
		
	}
	
	if ($match == FALSE)
	{
		echo '<li>Add <a href="http://virginiageneralassembly.gov/house/members/members.php?id='
			. $lis_id . '">' . $name . '</a></li>';
	}

}
