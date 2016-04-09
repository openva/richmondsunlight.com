<html>
<head>
	<meta http-equiv="refresh" content="2" />
</head>
<body>
<?php

/*
DANGER, WILL ROBINSON!
If a bill has no placenames in it, we never mark it as being name-less. The result is that we run
a query against Yahoo over and over and over, always getting no results. That costs $6/1,000, so
that's probably something we'll want to prevent.
*/

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

# Select all bills that contain a phrase concerning geography for which we don't already have
# location records stored.
$sql = 'SELECT bills.id, bills.number, bills.full_text, sessions.year
		FROM bills
		LEFT JOIN sessions
			ON bills.session_id=sessions.id
		WHERE 
			(
				(full_text LIKE "% Town of%") OR (full_text LIKE "% City of%")
				OR (full_text LIKE "% County of%") OR (full_text LIKE "% Towns of%")
				OR (full_text LIKE "% Cities of%") OR (full_text LIKE "% Counties of%")
				OR (full_text LIKE "% County%") OR (full_text LIKE "% City%")
			)
		AND
			(SELECT COUNT(*)
			FROM bills_places
			WHERE bill_id=bills.id) = 0
		AND bills.session_id=' . SESSION_ID . '
		AND bills.date_created >= (CURDATE() - INTERVAL 3 DAY)
		ORDER BY RAND()
		LIMIT 5';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{

	/*
	 * Connect to Memcached, as we may well be interacting with it during this session.
	 */
	$mc = new Memcached();
	$mc->addServer("127.0.0.1", 11211);

	# Set up cURL for the queries to follow.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POST, 1);
	
	while ($bill = mysql_fetch_array($result))
	{
		
		$bill = array_map('stripslashes', $bill);		
		$url = 'http://wherein.yahooapis.com/v1/document';
		
		# Define our fields.
		$fields = array(
			'documentURL' => 'http://www.richmondsunlight.com/downloads/bills/' . $bill['year'] . '/' . $bill['number'] . '.html',
			'autoDisambiguate' => 'true',
			'focusWoeId' => '12590337',
			'confidence' => '8',
			'outputType' => 'json',
			'appid' => YAHOO_KEY
		);
		foreach ($fields as $key=>$value)
		{
			$query_string .= $key.'='.urlencode($value).'&';
			rtrim($fields_string,'&');
		}
		
		# Tell cURL the URL to which we'll be POSTing.
		curl_setopt ($ch, CURLOPT_URL, $url);
		
		# Indicate the number of fields that we'll be providing content for.
		curl_setopt($ch,CURLOPT_POST, count($fields));
		
		# Pass the POST data.
		curl_setopt($ch,CURLOPT_POSTFIELDS, $query_string);
		
		# Get the data from cURL.
		ob_start();
		curl_exec($ch);
		curl_close($ch);
		$json = ob_get_contents();
		ob_end_clean();
		
		if ($json == FALSE)
		{
			continue;
		}
		
		$yahoo_response = json_decode($json, true);
		
		if ( ($yahoo_response == FALSE) || !isset($yahoo_response['document']) )
		{
			continue;
		}
		
		echo '<h1>'.$bill['year'].' '.$bill['number'].'</h1>';
		
		foreach ($yahoo_response['document'] as $key => $response)
		{
		
			# Mixed in with the named keys are numbered keys, and the numbered keys contain the
			# useful results. We just skip the non-numbered keys.
			if (!is_numeric($key))
			{
				continue;
			}
			
			echo '<h2>'.$key.'</h2>';
			echo '<pre>'.print_r($response, true).'</pre>';
			
			# If this found place isn't in the state of Virginia, we have no interest in it. Or if
			# it's the phrase "Commonwealth of Virginia" resulting in the town of Commonwealth being
			# looked up. Or if it's the town of Marshall, that's just a bill introduced by Bob
			# or Danny Marshall.
			if	(
					(strpos($response['placeDetails']['place']['name'], ', VA, US') === false)
					||
					(strpos($response['placeDetails']['place']['name'], 'Commonwealth') !== false)
					||
					(['placeDetails']['place']['name'] == 'Marshall')
				)
			{
				continue;
			}
			
			echo '<p>Extracting location from response.</p>';
			
			$place['latitude'] = $response['placeDetails']['place']['centroid']['latitude'];
			$place['longitude'] = $response['placeDetails']['place']['centroid']['longitude'];
			$place['name'] = str_replace(', VA, US', '', $response['placeDetails']['place']['name']);
			
			echo '<pre>'.print_r($place, true).'</pre>';
			
			////////////////////////
			// * Duplicates happen. You'd think Yahoo would filter them out, but they do not. Either
			//   unique the data that's going to be stored pre-storage or modify the DB to be OK
			//   with this.
			////////////////////////
			$sql = 'INSERT INTO bills_places
					SET bill_id='.$bill['id'].', placename="'.addslashes($place['name']).'",
					latitude='.$place['latitude'].', longitude='.$place['longitude'];
			mysql_query($sql);
			echo '<p>'.$sql.'</p>';
			
			/*
			 * Clear the bill from Memcached.
			 */
			$mc->delete('bill-' . $bill['id']);
			
			unset($place);
			
		}
		
	}
	
	# Shut down the cURL connection.
	curl_close($ch);
	
}
	
?>
</body>
</html>