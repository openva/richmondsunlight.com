<?php

# Retrieve the CSV data and save it to a local file. We make sure that it's non-empty because
# otherwise, if the connection fails, we end up with a zero-length file.
$bills = get_content('ftp://' . LIS_FTP_USERNAME . ':' . LIS_FTP_PASSWORD
	. '@legis.state.va.us/fromdlas/csv' . $dlas_session_id . '/BILLS.CSV');
if (empty($bills))
{
	pushover_alert('Bills missing from DLAS', 'BILLS.CSV doesn’t exist on legis.state.va.us.');
	die('No data found on DLAS’s FTP server.');
}

# If the MD5 value of the new file is the same as the saved file, then there's nothing to update.
if (md5($bills) == md5_file('bills.csv'))
{
	echo 'bills.csv has not been modified since it was last downloaded.';
	exit;
}

/*
 * Remove any white space.
 */
$bills = trim($bills);

/*
 * Save the bills locally.
 */
file_put_contents('bills.csv', $bills);

/*
 * Open the resulting file.
 */
$fp = fopen('bills.csv','r');

/*
 * Also, retrieve our saved serialized array of hash data, so that we can only update or insert
 * bills that have changed, or that are new.
 */
$hash_path = 'hashes/bills-' . SESSION_ID . '.md5';
if (file_exists($hash_path))
{
	$hashes = file_get_contents($hash_path);
	if ($hashes !== FALSE)
	{
		$hashes = unserialize($hashes);
	}
	else
	{
		$hashes = array();
	}
}
else
{
	$hashes = array();
}

/*
 * Connect to Memcached, as we may well be interacting with it during this session.
 */
$mc = new Memcached();
$mc->addServer("127.0.0.1", 11211);

/*
 * Set a flag that will allow us to ignore the header row.
 */
$first = 'yes';

/*
 * Step through each row in the CSV file, one by one.
 */
while (($bill = fgetcsv($fp, 1000, ',')) !== FALSE)
{
	
	# If this is something other than a header row, parse it.
	if (isset($first))
	{
		unset($first);
		continue;
	}
	
	###
	# Before we proceed any farther, see if this record is either new or different than last
	# time that we examined it.
	###
	$hash = md5(serialize($bill));
	$number = strtolower(trim($bill[0]));
	
	if ( isset($hashes[$number]) && ($hash == $hashes[$number]) )
	{
		continue;
	}
	else
	{
	
		$hashes[$number] = $hash;
		if (!isset($hashes[$number]))
		{
			echo '<p>Adding ';
		}
		else
		{
			echo '<p>Updating ';
		}
		echo strtoupper($number) .'.</p>';
		
	}
	
	# Provide friendlier array element names.
	$bill['number'] = strtolower(trim($bill[0]));
	$bill['catch_line'] = trim($bill[1]);
	$bill['chief_patron_id'] = substr(trim($bill[2]), 1);
	$bill['chief_patron'] = trim($bill[3]);
	$bill['last_house_committee'] = trim($bill[4]);
	$bill['last_house_date'] = strtotime(trim($bill[6]));
	$bill['last_senate_committee'] = trim($bill[7]);
	$bill['last_senate_date'] = strtotime(trim($bill[9]));
	$bill['passed_house'] = trim($bill[15]);
	$bill['passed_senate'] = trim($bill[16]);
	$bill['passed'] = trim($bill[17]);
	$bill['failed'] = trim($bill[18]);
	$bill['continued'] = trim($bill[19]);
	$bill['approved'] = trim($bill[20]);
	$bill['vetoed'] = trim($bill[21]);
	
	# The following are versions of the bill's full text. Only the first pair need be
	# present. But the remainder are there to deal with the possibility that the bill is
	# amended X times.
	$bill['text'][0]['number'] = trim($bill[22]);
	$bill['text'][0]['date'] = date('Y-m-d', strtotime(trim($bill[23])));
	if (!empty($bill[24])) $bill['text'][1]['number'] = trim($bill[24]);
	if (!empty($bill[25])) $bill['text'][1]['date'] = date('Y-m-d', strtotime(trim($bill[25])));
	if (!empty($bill[26])) $bill['text'][2]['number'] = trim($bill[26]);
	if (!empty($bill[27])) $bill['text'][2]['date'] = date('Y-m-d', strtotime(trim($bill[27])));
	if (!empty($bill[28])) $bill['text'][3]['number'] = trim($bill[28]);
	if (!empty($bill[29])) $bill['text'][3]['date'] = date('Y-m-d', strtotime(trim($bill[29])));
	if (!empty($bill[30])) $bill['text'][4]['number'] = trim($bill[30]);
	if (!empty($bill[31])) $bill['text'][4]['date'] = date('Y-m-d', strtotime(trim($bill[31])));
	if (!empty($bill[32])) $bill['text'][5]['number'] = trim($bill[32]);
	if (!empty($bill[33])) $bill['text'][5]['date'] = date('Y-m-d', strtotime(trim($bill[33])));
	
	# Determine if this was introduced in the House or the Senate.
	if ($bill['number']{0} == 'h')
	{
		$bill['chamber'] = 'house';
	}
	elseif ($bill['number']{0} == 's')
	{
		$bill['chamber'] = 'senate';
	}
	
	# Set the last committee to be the committee in the chamber
	# in which there was most recently activity.
	if (empty($bill['last_house_date']))
	{
		$bill['last_house_date'] = 0;
	}
	if (empty($bill['last_senate_date']))
	{
		$bill['last_senate_date'] = 0;
	}
	if ($bill['last_house_date'] > $bill['last_senate_date'])
	{
		$bill['last_committee'] = substr($bill['last_house_committee'], 1);
		$bill['last_committee_chamber'] = 'house';
	}
	else
	{
		$bill['last_committee'] = substr($bill['last_senate_committee'], 1);
		$bill['last_committee_chamber'] = 'senate';
	}
	
	# Determine the latest status.
	if ($bill['approved'] == 'Y')
	{
		$bill['status'] = 'approved';
	}
	elseif ($bill['vetoed'] == 'Y')
	{
		$bill['status'] = 'vetoed';
	}
	# Only flag the bill as continued if it's from after Feb. '08.  This will
	# need to be updated periodically.
	elseif ($bill['continued'] == 'Y')
	{
		if (($bill['last_house_date'] > strtotime('01 February 2008'))
			&& ($bill['last_senate_date'] > strtotime('01 February 2008')))
		{
			$bill['status'] = 'continued';
		}
		else
		{
			$bill['status'] = 'failed';
		}
	}
	elseif ($bill['failed'] == 'Y') $bill['status'] = 'failed';
	elseif ($bill['passed'] == 'Y') $bill['status'] = 'passed';
	elseif ($bill['passed_senate'] == 'Y') $bill['status'] = 'passed senate';
	elseif ($bill['passed_house'] == 'Y') $bill['status'] = 'passed house';
	elseif (!empty($bill['last_senate_committee']) || !empty($bill['last_house_committee']))
	{
		$bill['status'] = 'in committee';
	}
	else
	{
		$bill['status'] = 'introduced';
	}

	# Create an instance of HTML Purifier to clean up the text.
	$purifier = new HTMLPurifier();
	
	# Purify the HTML and trim off the surrounding whitespace.
	$bill['catch_line'] = trim($purifier->purify($bill['catch_line']));
	
	# Prepare the data for the database.
	$bill = array_map_multi('mysql_real_escape_string', $bill);
	
	# Check to see if the bill is already in the database.
	$sql = 'SELECT id
			FROM bills
			WHERE number="'.$bill['number'].'" AND session_id='.$session_id;
	$result = mysql_query($sql);

	if (mysql_num_rows($result) > 0)
	{
		$sql = 'UPDATE bills SET ';
		$existing_bill = mysql_fetch_array($result);
		$sql_suffix = ' WHERE id=' . $existing_bill['id'];
		
		# Now that we know we're updating a bill, rather than adding a new one, delete the bill from
		# Memcached.
		$mc->delete('bill-' . $existing_bill['id']);
		
	}
	else
	{
		$sql = 'INSERT INTO bills SET date_created=now(), ';
	}
	
	# Now create the code to insert the bill or update the bill, depending
	# on what the last query established for the preamble.
	$sql .= 'number="'.$bill['number'].'", session_id="'.$session_id.'",
			chamber="'.$bill['chamber'].'", catch_line="'.$bill['catch_line'].'",
			chief_patron_id=
				(SELECT id
				FROM representatives
				WHERE
					(lis_id = "'.$bill['chief_patron_id'].'"
					OR
					lis_shortname = "'.strtolower($bill['chief_patron']).'")
				AND (
						(date_ended IS NULL)
						OR
						(YEAR(date_ended)+1 = YEAR(now()))
						OR
						(YEAR(date_ended) = YEAR(now()))
					)
				AND chamber = "'.$bill['chamber'].'"),
			last_committee_id=
				(SELECT id
				FROM committees
				WHERE lis_id = "'.$bill['last_committee'].'" AND parent_id IS NULL
				AND chamber = "'.$bill['last_committee_chamber'].'"),
			status="'.$bill['status'].'"';
	if (isset($sql_suffix))
	{
		$sql = $sql . $sql_suffix;
	}
	
	$result = mysql_query($sql);
	
	if ($result === FALSE)
	{
		echo '<p style="color: #f00;">'.$bill['number'].' failed:</p><p><code>'.$sql.'</code></p>';
		unset($hashes[$number]);
	}
	
	else
	{
		
		# Get the last bill insert ID.
		if (!isset($existing_bill['id']))
		{
			$bill['id'] = mysql_insert_id();
		}
		else
		{
			$bill['id'] = $existing_bill['id'];
		}
		
		# Create a bill full text record for every version of the bill text that's filed.
		for ($i=0; $i<count($bill['text']); $i++)
		{
			if (!empty($bill['text'][$i]['number']) && !empty($bill['text'][$i]['date']))
			{
				$sql = 'INSERT INTO bills_full_text
						SET bill_id = '.$bill['id'].', number="'.$bill['text'][$i]['number'].'",
						date_introduced="'.$bill['text'][$i]['date'].'", date_created=now()
						ON DUPLICATE KEY UPDATE date_introduced=date_introduced';
				mysql_query($sql);
			}
		}
	}
		
	# Unset those variables to avoid reuse.
	unset($sql_suffix);
	unset($bill['id']);
	unset($existing_bill);
	
} // end looping through lines in this CSV file

# Close the CSV file.
fclose($fp);

# Store our per-bill hashes array to a file, so that we can open it up next time and see which
# bills have changed.
file_put_contents($hash_path, serialize($hashes));
