<?php

###
# UPDATE BILLS' FULL TEXT
# What we're actually doing here is updating the bills_full_text table. Then we use that
# to synch up the data in the bills table later on. We don't bother with any bill's text that, after
# twenty tries, we just can't manage to retrieve.
###
/* This only works if there's already an entry in bills_full_text. */
$sql = 'SELECT bills_full_text.id, bills_full_text.number, sessions.lis_id
		FROM bills_full_text
		LEFT JOIN bills
			ON bills_full_text.bill_id = bills.id
		LEFT JOIN sessions
			ON bills.session_id = sessions.id
		WHERE bills_full_text.text IS NULL AND bills_full_text.failed_retrievals < 20
		ORDER BY bills_full_text.failed_retrievals ASC, sessions.year DESC,
		bills.date_introduced DESC, bills_full_text.date_introduced DESC
		LIMIT 10';

$result = mysql_query($sql);

if (mysql_num_rows($result) > 0)
{
	
	# Fire up HTML Purifier.
	//$purifier = new HTMLPurifier();
	
	while ($text = mysql_fetch_array($result))
	{

		# Retrieve the full text.
		$url = 'http://leg1.state.va.us/cgi-bin/legp504.exe?' . $text['lis_id'] . '+ful+'
			. strtoupper($text['number']);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$full_text = curl_exec($ch);
		curl_close($ch);
		
		# Convert the legislature's Windows-1252 text to UTF-8.
		$full_text = iconv('windows-1252', 'UTF-8', $full_text);
		
		# Convert into an array.
		$full_text = explode("\n", $full_text);
		
		# Clean up the bill's full_text.
		$full_text_clean = '';
		for ($i=0; $i<count($full_text); $i++)
		{
			if (!isset($start))
			{
				if (stristr($full_text[$i], 'HOUSE BILL NO. ')) $start = TRUE;
				elseif (stristr($full_text[$i], 'SENATE BILL NO. ')) $start = TRUE;
				elseif (stristr($full_text[$i], 'SENATE JOINT RESOLUTION NO. ')) $start = TRUE;
				elseif (stristr($full_text[$i], 'HOUSE JOINT RESOLUTION NO. ')) $start = TRUE;
				elseif (stristr($full_text[$i], 'SENATE RESOLUTION NO. ')) $start = TRUE;
				elseif (stristr($full_text[$i], 'HOUSE RESOLUTION NO. ')) $start = TRUE;
				elseif (stristr($full_text[$i], 'VIRGINIA ACTS OF ASSEMBLY')) $start = TRUE;
			}
				
			# Finally, we're at the text of the bill.
			if (isset($start))
			{
				# This is the end of the text.
				if (stristr($full_text[$i], '</body></html>'))
				{
					break;
				}
				
				# Otherwise, add this line to our bill text.
				else
				{
					
					# Determine where the header text ends and the actual law begins.
					if (stristr($full_text[$i], 'Be it enacted by'))
					{
						$law_start = TRUE;
					}
					
					if ( isset($law_start) && ($law_start == TRUE) )
					{
						$full_text[$i] = str_replace('<i>', '<ins>', $full_text[$i]);
						$full_text[$i] = str_replace('</i>', '</ins>', $full_text[$i]);
					}
					
					# Finally, append this line to our cleaned-up, stripped-down text.
					$full_text_clean .= $full_text[$i].' ';
				}
			}
		}
		unset($full_text);
		unset($start);
		unset($law_start);
		
		# Strip out unacceptable tags and prefix the description with its two prefix
		# tags.  Then provide a domain name for all links.
		$full_text = trim(strip_tags($full_text_clean, '<p><b><i><em><strong><u><a><br><center><s><strike><ins>'));
		
		if (!empty($full_text))
		{
			
			# Replace relative links with absolute ones.
			$full_text = str_ireplace('href="/', 'href="http://lis.virginia.gov/', $full_text);

			# Replace links to the state code with links to Virginia Decoded.
			$full_text = str_ireplace('href="http://law.lis.virginia.gov/vacode', 'href="https://vacode.org', $full_text);
			
			# Any time that we've just got a question mark hanging out, that should be a section
			# symbol.
			$full_text = str_replace(' ? ', ' &sect; ', $full_text);
			
			# Put the data back into the database, but clean it up first.
			$full_text = trim($full_text);
			$full_text = mysql_real_escape_string($full_text);
			
			if (!empty($full_text))
			{
				# We store the bill's text, and also reset the counter that tracks failed attempts
				# to retrieve the text from the legislature's website.
				$sql = 'UPDATE bills_full_text
						SET text="'.$full_text.'", failed_retrievals=0
						WHERE id='.$text['id'];
				$result2 = mysql_query($sql);
				if (!$result2)
				{
					echo '<p>Insertion of '.strtoupper($text['number']).' text failed.</p>';
				}
				else
				{
					echo '<p>Insertion of '.strtoupper($text['number']).' text succeeded.</p>';
				}
			}
			
			# Unset the variables that we used here.
			unset($start);
			unset($full_text);
			unset($full_text_clean);
			
			sleep(3);
			
		}
		else
		{
		
			# Increment the failed retrievals counter.
			$sql = 'UPDATE bills_full_text
					SET failed_retrievals = failed_retrievals+1
					WHERE id='.$text['id'];
			mysql_query($sql);
			
			echo '<p><a href="'. $url . '">Full text of '.$text['number'].'</a> came up blank.</p>';
			
		}
	}
}

?>