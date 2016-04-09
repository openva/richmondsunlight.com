<?php

###
# UPDATE BILL SUMMARIES
# Pick a random selection of the bills for which we lack summaries and retrieve their summaries.
###

$sql = 'SELECT bills.id, bills.number, sessions.lis_id
		FROM bills
		LEFT JOIN sessions
			ON bills.session_id = sessions.id
		WHERE bills.summary IS NULL AND bills.session_id = '.$session_id.'
		ORDER BY RAND()
		LIMIT 15';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	
	while ($bill = mysql_fetch_array($result))
	{
		
		# Intialize a cURL session.
		$ch = curl_init('http://leg1.state.va.us/cgi-bin/legp504.exe?'.$bill['lis_id'].'+sum+'
			.strtoupper($bill['number']));
		
		# Retrieve the summary.
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$summary = curl_exec($ch);
		curl_close($ch);
		
		# Convert each into an array.
		$summary = explode("\r", $summary);
		
		# Clean up the summary.
		$summary_clean = '';
		for ($i=0; $i<count($summary); $i++)
		{
			if (!isset($start))
			{
				if (stristr($summary[$i], 'Summary as'))
				{
					$start = 'yes';
				}
			}
			else
			{
				if (stristr($summary[$i], 'Full text:'))
				{
					break;
				}
				else
				{
					$summary[$i] = str_replace("\n", ' ', $summary[$i]);
					$summary_clean .= $summary[$i];
				}
			}
		}
		unset($summary);
		unset($start);

		# Remove the paragraph tags, newlines, NBSPs and double spaces.
		$summary = str_replace('<p>', '', $summary_clean);
		$summary = str_replace('</p>', '', $summary);
		$summary = str_replace("\r", ' ', $summary);
		$summary = str_replace("\n", ' ', $summary);
		$summary = str_replace('&nbsp;', ' ', $summary);
		$summary = str_replace('  ', ' ', $summary);
		$summary = str_replace('\u00a0', ' ', $summary);
		
		# There is often an HTML mistake in this tag, so we perform this replacement after
		# running HTML Purifier, not before.
		$summary = str_replace('<br clear="all" /> ', ' ', $summary);
		
		$summary = strip_tags($summary, '<b><i><em><strong>');

		# Run the summary through HTML Purifier.
		$purifier = new HTMLPurifier();
		$summary = $purifier->purify($summary);
		
		# Clean up the bolding, so that we don't bold a blank space.
		$summary = str_replace(' </b>', '</b> ', $summary);
		
		# Trim off any whitespace.
		$summary = trim($summary);
		
		# Hack off a hanging non-breaking space, if there is one.
		// This just isn't working, and I have no idea why.
		if (substr($summary, -7) == ' &nbsp;')
		{
			$summary = substr($summary, 0, -8);
		}

		# Create an instance of HTML Purifier to clean up the text.
		$purifier = new HTMLPurifier();
		
		# Purify the HTML.
		$summary = $purifier->purify($summary);
		
		# Put the data back into the database.
		if (!empty($summary))
		{
			$sql = 'UPDATE bills
					SET summary="'.mysql_real_escape_string($summary).'"
					WHERE id='.$bill['id'];
			$result2 = mysql_query($sql);
			if (!$result2)
			{
				echo '<p>Insertion of '.strtoupper($bill['number']).' summary failed.</p>';
			}
			else
			{
				echo '<p>Insertion of '.strtoupper($bill['number']).' succeeded.</p>';
			}
		}
		else
		{
			echo '<p><a href="http://leg1.state.va.us/cgi-bin/legp504.exe?'.$bill['lis_id'].'+sum+'.
				strtoupper($bill['number']).'">Summary of '.strtoupper($bill['number'])
				.'</a> came up blank.</p>';
		}
		
		# Unset the variables that we used here.
		unset($start);
		unset($summary);
		unset($summary_clean);
		
		sleep(3);
	}
}

?>
