<?php

class Import
{
	
	# Retrieve a bill's text from the legislature's website.
	function get_bill_text()
	{
		
		if (!isset($this->bill_number) || !isset($this->lis_session_id))
		{
			return false;
		}
	
		# Retrieve the full text.
		$ch = curl_init('http://leg1.state.va.us/cgi-bin/legp504.exe?'.$this->lis_session_id.'+ful+'
			.strtoupper($this->bill_number));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$text = curl_exec($ch);
		curl_close($ch);
	
		# Convert into an array.
		$text = explode("\n", $text);
		
		# Extract just the bill's text from the HTML.
		$text_clean = '';
		for ($i=0; $i<count($text); $i++)
		{
			if (!isset($start))
			{
				# These are the candidates strings of text that indicate that the bill is beginning
				# (as opposed to the HTML and general navigational text that precedes it).
				$preambles = array(
					'HOUSE BILL NO. ',
					'SENATE BILL NO. ',
					'SENATE JOINT RESOLUTION NO. ',
					'HOUSE JOINT RESOLUTION NO. ',
					'SENATE RESOLUTION NO. ',
					'HOUSE RESOLUTION NO. ');
				foreach ($preambles as $preamble)
				{
					if (stristr($text[$i], $preamble))
					{
						$start = TRUE;
						break;
					}
				}
			}
				
			# Finally, we're at the text of the bill.
			if (isset($start))
			{
				# This is the end of the text.
				if (stristr($text[$i], '</body></html>'))
				{
					break;
				}
				
				# Otherwise, add this line to our bill text.
				else
				{
					
					# Determine where the header text ends and the actual law begins.
					if (stristr($text[$i], 'Be it enacted by'))
					{
						$law_start = TRUE;
					}
					
					if ($law_start == TRUE)
					{
						$text[$i] = str_replace('<i>', '<ins>', $text[$i]);
						$text[$i] = str_replace('</i>', '</ins>', $text[$i]);
					}
					
					# Finally, append this line to our cleaned-up, stripped-down text.
					$text_clean .= $text[$i].' ';
				}
			}
		}
		unset($text);
		unset($start);
		unset($law_start);
		
		# Strip out unacceptable tags.
		$text = trim(strip_tags($text_clean, '<p><b><i><em><strong><u><a><br><center><s><strike><ins>'));
		
		# In the unlikely possibility that we are now left with no text at all, then give up.
		if (empty($text))
		{
			return false;
		}
		
		# Provide a domain name for all links.
		$text = str_ireplace('href="/', 'href="http://leg1.state.va.us/', $text);
		
		# Make the text available within the scope of the class.
		$this->text = $text;
	}
	
	# Take the legislature's HTML and make it less bad.
	function clean_bill_text()
	{
		
		if (!isset($this->text))
		{
			return false;
		}
		
		# Convert the legislature's Windows-1252 text to UTF-8.
		// Not necessary when using HTML Purifier.
		//$text = iconv('windows-1252', 'UTF-8', $text);
		
		# Fire up HTML Purifier.
		$purifier = new HTMLPurifier();

		# Run the text through HTML Purifier.
		$this->text = $purifier->purify($this->text);
	}
}

?>