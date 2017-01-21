 <?php

// Store the prior match and try it as the first option on the next time around. No need to
// start from zero when we know there's a pretty good chance that each name is going to
// occur repeatedly.

set_time_limit(120);
	
# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');
	
# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# Insert a matched chyron and ID.
function insert_match($linked_id, $chyron_id)
{
	if (empty($chyron_id) || empty($linked_id))
	{
		return false;
	}
	$sql = 'UPDATE video_index
			SET linked_id=' . $linked_id . '
			WHERE id=' . $chyron_id;
	$result = mysql_query($sql);
	if (!$result)
	{
		echo '<p style="color: #f00;">Insert of chyron ID ' . $chyron_id . ' failed.</p>';
		return false;
	}
}


###
# MATCH UNRESOLVED BILLS
###

# If we've passed an ID, use that.
if (isset($_GET['id']))
{
	$sql = 'SELECT video_index.file_id, files.date, files.chamber
			FROM video_index
			LEFT JOIN files
				ON video_index.file_id=files.id
			WHERE video_index.type="bill" AND video_index.linked_id IS NULL
			AND files.id=' . $_GET['id'] . '
			GROUP BY file_id';
}
else
{

	# Pick a video file for which we have unresolved bills, selecting the one that has the largest
	# number of unresolved bills.
	$sql = 'SELECT video_index.file_id, files.date, files.chamber, COUNT(*) AS number
			FROM video_index
			LEFT JOIN files
				ON video_index.file_id=files.id
			WHERE
				video_index.type="bill" AND
				video_index.linked_id IS NULL AND
				ignored = "n"
			GROUP BY file_id
			HAVING number > 100
			ORDER BY RAND()
			LIMIT 1';
}

$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	
	$video = mysql_fetch_array($result);
	
	# Get a list of all bills that were addressed on that date (in bills_status).
	$sql = 'SELECT DISTINCT bills_status.bill_id AS id, bills.number
			FROM bills_status
			LEFT JOIN bills
				ON bills_status.bill_id = bills.id
			WHERE bills_status.date = "' . $video['date'] . '"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{

		# If we can't get the bills heard on this date (generally because we're
		# parsing the video on the same ay that it was recorded), then use all
		# bill numbers from this session, instead.
		$sql = 'SELECT id, number
				FROM bills
				WHERE session_id = (
					SELECT id
					FROM sessions
					WHERE date_started <= ' . $video['date'] . '
					AND (date_ended >= ' . $video['date'] . '
						OR 
						date_ended IS NULL)
				)';
		$result = mysql_query($sql);

	}
	
	# Build up an array of bills, using the ID as the key and the number as the content.
	while ($bill = mysql_fetch_array($result))
	{
		$bills[$bill{'id'}] = $bill['number'];
	}
	
	# Get a list of all bills that are in the legislature, period.
	$sql = 'SELECT DISTINCT id, number
			FROM bills
			WHERE session_id=
				(SELECT id
				FROM sessions
				WHERE "'.$video['date'].'" > date_started
				AND ("'.$video['date'].'" < date_ended OR date_ended IS NULL)
				ORDER BY date_started DESC
				LIMIT 1)';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
	
		# Build up an array of bills, using the ID as the key and the number as the content.
		while ($bill = mysql_fetch_array($result))
		{
			$all_bills[$bill{id}] = $bill['number'];
		}
	}
	
	# Step through each bill chyron.
	$sql = 'SELECT id, raw_text
			FROM video_index
			WHERE file_id='.$video['file_id'].' AND type="bill" AND linked_id IS NULL
			AND ignored = "n"
			ORDER BY time ASC';

	$result = mysql_query($sql);
	while ($chyron = mysql_fetch_array($result))
	{
		
		# Strip out any spaces in the bill number -- just compare the bills straight up. Although
		# bill numbers in the chyrons have spaces between the prefix ("HB") and the number ("1"),
		# the OCR software doesn't always catch that. Better to just ignore the spaces entirely.
		$chyron['raw_text'] = str_replace(' ', '', $chyron['raw_text']);
		
		# Also, we're dealing with this in lower case.
		$chyron['raw_text'] = strtolower($chyron['raw_text']);
		
		# Make any obvious corrections that tend to occur with OCR software.
		if (
			(substr($chyron['raw_text'], 0, 2) == 's8')
			||
			(substr($chyron['raw_text'], 0, 2) == '58')
			||
			(substr($chyron['raw_text'], 0, 2) == 'ss')
			||
			(substr($chyron['raw_text'], 0, 2) == '$8')
		)
		{
			$chyron['raw_text'] = 'SB'.substr($chyron['raw_text'], 2);
		}
		elseif (
			(substr($chyron['raw_text'], 0, 3) == 'sir')
			||
			(substr($chyron['raw_text'], 0, 3) == 'sjr')
		)
		{
			$chyron['raw_text'] = 'SJ'.substr($chyron['raw_text'], 3);
		}
		elseif (
			(substr($chyron['raw_text'], 0, 3) == 'hjr')
			||
			(substr($chyron['raw_text'], 0, 3) == 'pur')
			||
			(substr($chyron['raw_text'], 0, 3) == 'fur')
			||
			(substr($chyron['raw_text'], 0, 3) == 'i-ur')
		)
		{
			$chyron['raw_text'] = 'hj'.substr($chyron['raw_text'], 3);
		}
		elseif (
			(substr($chyron['raw_text'], 0, 2) == 'I ')
			||
			(substr($chyron['raw_text'], 0, 2) == '| ')
			||
			(substr($chyron['raw_text'], 0, 2) == '! ')
			||
			(substr($chyron['raw_text'], 0, 2) == '; ')
		)
		{
			$chyron['raw_text'] = substr($chyron['raw_text'], 2);
		}
			
		
		# If there is a direct match with a bill dealt with on that day, insert it.
		$bill_id = array_search(strtolower($chyron['raw_text']), $bills);
		if ( ($bill_id !== FALSE) && !empty($bill_id) )
		{
			echo '<li>'.$chyron['raw_text'].' matched to '.$bills[$bill_id].' ('.$bill_id.')</li>';
			insert_match($bill_id, $chyron['id']);
		}
		
		# If we couldn't match it with a bill dealt with on that day, see if we can match it with
		# any bill introduced that year. This helps to allow bills to be recognized in spite of
		# legislative recordkeeping errors.
		else
		{
			$bill_id = array_search(strtolower($chyron['raw_text']), $all_bills);
			if ( ($bill_id !== FALSE) && !empty($bill_id) )
			{
				echo '<li>'.$chyron['raw_text'].' matched to '.$bills[$bill_id].' ('.$bill_id.')</li>';
				insert_match($bill_id, $chyron['id']);
			}
		}
	}

	# If any single unresolved bill chyrons are found that are surrounded by resolved chyrons that
	# are resolved on both sides, then we just fill in that gap with the obvious chyron, which is
	# the bill number on either side of it.
	$sql = 'SELECT id, time
			FROM video_index
			WHERE file_id = ' . $video['file_id'] . '
			AND TYPE = "bill"
			AND linked_id IS NULL';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		while ($unresolved = mysql_fetch_array($result))
		{
			# Retrieve a list of linked IDs present for fifteen seconds on either side of this
			# unknown chyron.
			$sql = 'SELECT DISTINCT linked_id
					FROM video_index
					WHERE file_id = ' . $video['file_id'] . ' AND type="bill" AND linked_id IS NOT NULL
					AND
					(
						(TIMEDIFF("' . $unresolved['time'] . '", time)<=15)
						AND
						(TIMEDIFF("' . $unresolved['time'] . '", time)>=-15)
					)';
			$result2 = mysql_query($sql);
			# If we've got just one row—which is to say that there's only one bill discussed in this
			# thirty-second window—then we'll take it.
			if (mysql_num_rows($result2) === 1)
			{
				$resolved = mysql_fetch_array($result2);
				insert_match($resolved['linked_id'], $unresolved['id']);
			}
		}
	}
	
	echo '<p>Finished matching bill chyrons.</p>';
	
	# Store the new bill number chyrons for this video.
	if (isset($video['file_id']))
	{
		# Create a new instance of the Video class.
		$vid = new Video;
		$vid->id = $video['file_id'];
		$vid->store_clips();
		
		echo '<p>(Re)indexed '.$vid->clip_count.' clips, cued by updating bill number chyrons, and
			stored those clips.</p>';
		
	}
	
}


###
# MATCH UNRESOLVED LEGISLATORS
###

# Select a listing of all legislators, parties, and placenames.
$sql = 'SELECT representatives.id, representatives.chamber, representatives.name,
		representatives.party, representatives.place, districts.number AS district
		FROM representatives
		LEFT JOIN districts
			ON representatives.district_id = districts.id';
// LIMIT THIS TO THOSE LEGISLATORS WHO ARE IN OFFICE ON THE DATE OF THIS VIDEO
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	# Initalize the arrays.
	$legislators = array();
	
	# Iterate through the MySQL results and store them in an array.
	while ($legislator = mysql_fetch_array($result))
	{
		
		$legislator = array_map('stripslashes', $legislator);
		
		# Depending on the chamber, assign the legislator's prefix.
		if ($legislator['chamber'] == 'house')
		{
			$legislator['prefix'] = 'Del.';
		}
		else
		{
			$legislator['prefix'] = 'Sen.';
		}
		
		# Assemble the array of legislator data into the same format as the chyron text, so that
		# we can do a direct comparison later.
		$legislator['complete'] = $legislator['prefix'].' '.pivot($legislator['name'])."\r"
			.$legislator['place'].' ('.$legislator['party'].'-'.$legislator['district'].')';
			
		# Append this legislator to the array storing all of them.
		$legislators[] = $legislator;
	}
}

# Select a list of legislators by last name, party, and placename. This is for those times when
# legislative video producers decided to identify legislators only by last name, replacing the
# abbreviated title ("Sen.") with the full title ("Senator"), and failing to specify the district #.
$sql = 'SELECT representatives.id, representatives.chamber, representatives.name,
		representatives.party, representatives.place, districts.number AS district
		FROM representatives
		LEFT JOIN districts
			ON representatives.district_id = districts.id';
// LIMIT THIS TO THOSE LEGISLATORS WHO ARE IN OFFICE ON THE DATE OF THIS VIDEO
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	
	# Iterate through the MySQL results and store them in an array.
	while ($legislator = mysql_fetch_array($result))
	{
		
		$legislator = array_map('stripslashes', $legislator);
		
		# Depending on the chamber, assign the legislator's prefix.
		if ($legislator['chamber'] == 'house')
		{
			$legislator['prefix'] = 'Delegate';
		}
		else
		{
			$legislator['prefix'] = 'Senator';
		}
		
		# Extract the last name of the legislator.
		$tmp = explode(',', $legislator['name']);
		$legislator['last_name'] = $tmp[0];
		
		# Assemble the array of legislator data into the same format as the chyron text, so that
		# we can do a direct comparison later.
		$legislator['complete'] = $legislator['prefix'].' '.$legislator['last_name']."\r"
			.' ('.$legislator['party'].') '.$legislator['place'];

	}

}


# Select the raw text for the top 500 IDd legislators and append that to our array.
$sql = 'SELECT raw_text, linked_id, COUNT(*) AS number
		FROM video_index
		WHERE type = "legislator"
		AND linked_id IS NOT NULL 
		GROUP BY raw_text
		ORDER BY number DESC
		LIMIT 500';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{

	$priors = array();
	while ($tmp = mysql_fetch_array($result))
	{
		$tmp['raw_text'] = stripslashes($tmp['raw_text']);
		# We can't use a newline in an array key.
		$tmp['raw_text'] = str_replace("\n", ' ', $tmp['raw_text']);
		$priors[$tmp{raw_text}] = $tmp['linked_id'];
	}

}

# Select the raw text for the past 2,000 successfully IDd legislators and append that to our array.
$sql = 'SELECT DISTINCT raw_text, linked_id
		FROM video_index
		WHERE TYPE = "legislator" AND linked_id IS NOT NULL 
		ORDER BY date_created DESC
		LIMIT 2000';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{

	while ($tmp = mysql_fetch_array($result))
	{

		$tmp['raw_text'] = stripslashes($tmp['raw_text']);
		# We can't use a newline in an array key.
		$tmp['raw_text'] = str_replace("\n", ' ', $tmp['raw_text']);
		$priors[$tmp{raw_text}] = $tmp['linked_id'];

	}

}

# Select the last 5,000 unresolved legislator chyrons that don't contain known noisewords.
$sql = 'SELECT id, raw_text
		FROM video_index
		WHERE linked_id IS NULL AND type="legislator"
		AND ignored = "n"
		AND raw_text NOT LIKE "%Virginia Senate%" AND raw_text NOT LIKE "%Schaar%"
		AND raw_text NOT LIKE "%Delegates%" AND raw_text NOT LIKE "%At Ease%"
		AND raw_text NOT LIKE "%Reverend%" AND raw_text NOT LIKE "%Rabbi%"
		AND raw_text NOT LIKE "%in Recess%" AND raw_text NOT LIKE "%at Ease%"
		ORDER BY date_created DESC
		LIMIT 5000';
$result = mysql_query($sql);
# If there are no chyrons in need of resolution, then we can stop right now. (This is vanishingly
# unlikely.)
if (mysql_num_rows($result) == 0)
{
	exit;
}
while ($chyron = mysql_fetch_array($result))
{

	$chyron['raw_text'] = stripslashes($chyron['raw_text']);
	
	# Break up the chyron text into the first and second lines, the first dealing with who the
	# legislator is (e.g. "Del. John Q. Smith"), the second dealing (mostly) with where the
	# legislator represents (e.g. "Springfield (I-1)").
	$tmp = explode("\n", $chyron['raw_text']);
	$chyron['name'] = $tmp[0];
	$chyron['place'] = $tmp[1];
	unset($tmp);
	
	# First, attempt a straight match of the text.
	foreach ($legislators as $legislator)
	{
		# If we can find a legislator text string that's identical to the chyron, then we can stop
		# right here.
		if ($legislator['complete'] == $chyron['raw_text'])
		{

			echo '<li>Match found via a straight match. '.$legislator['complete'].' == '.$chyron['raw_text'].'</li>';
			insert_match($legislator['id'], $chyron['id']);
			next;

		}
	}
	
	# Second, attempt to use our lookup table of prior conversions, assuming that we have one.
	if (isset($priors))
	{

		# We've stored the raw text of the prior as the array key, but minus the newline, since we
		# can't use newlines in an array key. So we need to compare with that in mind.
		$tmp = str_replace("\n", ' ', $chyron['raw_text']);
		if (array_key_exists($tmp, $priors) === true)
		{
			echo '<li>Match found among prior matches. ('.$tmp.' == '.$chyron['raw_text'].')</li>';
			insert_match($priors[$tmp], $chyron['id']);
			next;
		}

	}
	
	# Third, get a listing of all direct matches within 20%.
	if (isset($legislators))
	{

		$matches = array();
		foreach ($legislators as $index => $legislator)
		{

			# If the Levenshtein distance is within 20% of the string length.
			if (levenshtein($legislator['complete'], $chyron['raw_text']) <= (strlen($chyron['raw_text']) * .2))
			{
				echo '<li>Match found within 80% confidence. ('.$tmp.' == '.$chyron['raw_text'].')</li>';
				# Store this match.
				$matches[$index] = levenshtein($legislator['complete'], $chyron);
			}

		}
		
		# If we've made any matches.
		if (count($matches) > 0)
		{
			
			# Sort by the strength of the match.
			asort($matches);
			
			reset($matches);
			$index = key($matches);
			$match = current($matches);
			$match = $legislators[$index]['id'];
			
			insert_match($match, $chyron['id']);
			next;
		}
	}
	
	# Fourth, get a listing of all prior matches within 30%.
	if (isset($priors))
	{

		# We've stored the raw text of the prior as the array key, but minus the newline, since we
		# can't use newlines in an array key. So we need to compare with that in mind.
		$tmp = str_replace("\n", ' ', $chyron['raw_text']);
		
		$matches = array();
		foreach ($priors as $text => $id)
		{
			# If the Levenshtein distance is within 15% of the string length. You might be tempted
			# to be more liberal in what you'll accept. Don't. "Senator Saslaw (D) Fairfax County"
			# is 81.8% identical to "Senator Howell (D) Fairfax County."
			if (levenshtein($text, $tmp) <= (strlen($tmp) * .15))
			{
				echo '<li>Match found within 85% confidence. ('.$tmp.' == '.$text.')</li>';
				# Store this match.
				$matches[$id] = levenshtein($text, $tmp);
			}
		}
		
		# If we've made any matches.
		if (count($matches) > 0)
		{
			
			# Sort by the strength of the match.
			asort($matches);
			
			reset($matches);
			$legislator_id = key($matches);
			
			insert_match($legislator_id, $chyron['id']);
			next;

		}

	}
	
}

echo '<p>Finished matching legislator chyrons.</p>';


# Create a new instance of the Video class.
$video = new Video;

# Get a list of every file that is not currently indexed in the video_clips table.
$sql = 'SELECT DISTINCT video_index.file_id AS id
		FROM video_index
		LEFT JOIN video_clips
			ON video_index.file_id = video_clips.file_id
		WHERE video_clips.file_id IS NULL';
$result = mysql_query($sql);
while ($file = mysql_fetch_array($result))
{

	$video->id = $file['id'];
	$video->store_clips();
	echo '<p>Indexed video clips for file '.$file['id'].'.</p>';

}

if (isset($_GET['id']))
{

	$video->id = $_GET['id'];
	$video->store_clips();
	echo '<p>Indexed video clips for file '.$file['id'].'.</p>';
	
}
