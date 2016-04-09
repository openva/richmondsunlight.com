<?php

	###
	# Retrieve and Store Minutes
	# 
	# PURPOSE
	# Retrieves the minutes from every meeting of the House and Senate and
	# stores them in the database.
	# 
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once('../includes/settings.inc.php');
	include_once('../includes/functions.inc.php');
	include_once('../includes/htmlpurifier/HTMLPurifier.auto.php');
	
	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.
	@connect_to_db();
	
	# PAGE CONTENT
	$sql = 'SELECT date, chamber
			FROM minutes';
	$result = mysql_query($sql);
	if (@mysql_num_rows($result) > 0)
	{
		while ($tmp = mysql_fetch_array($result))
		{
			$past_minutes[] = $tmp;
		}
	}
	
	$chambers['house'] = 'http://vacap.legis.virginia.gov/chamber.nsf/'.SESSION_LIS_ID.'HMinutes?OpenForm';
	$chambers['senate'] = 'http://leg1.state.va.us/cgi-bin/legp504.exe?ses='.SESSION_LIS_ID.'&typ=lnk&val=07';
	
	foreach ($chambers as $chamber => $listing_url)
	{
		
		# Begin by connecting to the appropriate session page.
		$raw_html = get_content($listing_url);
		$raw_html = explode("\n", $raw_html);
		
		# Iterate through every line in the HTML.
		foreach ($raw_html as &$line)
		{
			
			# Check if this line contains a link to the minutes for a given date.
			if ($chamber == 'house')
			{
				ereg('<a href="/chamber.nsf/([a-z0-9]{32})/([a-z0-9]{32})\?OpenDocument">([A-Za-z]+), ([A-Za-z]+) ([0-9]+), ([0-9]{4})</a>', $line, $regs);
			}
			elseif ($chamber == 'senate')
			{
				ereg('<a href="/cgi-bin/legp504.exe\?([0-9]{3})\+min\+([A-Za-z0-9]+)">([A-Za-z]+) ([0-9]+), ([0-9]{4})</a>', $line, $regs);
			}
			
			# We've found a match.
			if (isset($regs))
			{
				
				# Pull out the source URL and the date from the matched string.
				if ($chamber == 'house')
				{
					$source_url = 'http://vacap.legis.virginia.gov/chamber.nsf/'.$regs[1].'/'.$regs[2].'?OpenDocument';
					$date = date('Y-m-d', strtotime($regs[4].' '.$regs[5].' '.$regs[6]));
					//echo '<strong>House</strong><pre>'.print_r($regs, true).'</pre>';
				}
				elseif ($chamber == 'senate')
				{
					$source_url = 'http://leg1.state.va.us/cgi-bin/legp504.exe?'.$regs[1].'+min+'.$regs[2];
					$date = date('Y-m-d', strtotime($regs[3].' '.$regs[4].' '.$regs[5]));
					//echo '<strong>Senate</strong><pre>'.print_r($regs, true).'</pre>';
				}
				
				# Determines if this is a duplicate. If a match is found, the "repeat" flag is set.
				for ($i=0; $i<count($past_minutes); $i++)
				{
					if (($past_minutes[$i]['chamber'] == $chamber) && ($past_minutes[$i]['date'] == $date))
					{
						$repeat = TRUE;
						break;
					}
				}
				
				# If the repeat flag is set then we've seen these minutes before, in which case continue
				# to the next line in the minutes listing.
				if ($repeat === TRUE)
				{
					unset($repeat);
					unset($date);
					unset($source_url);
					unset($regs);
					continue;
				}
				
				# Retrieve and clean up the minutes.
				$minutes = get_content($source_url);
				
				# If the query was successful.
				if ($minutes != false)
				{
					
					# Strip out the bulk of the markup. We allow the HR tag because we sometimes use
					# it as a marker for where the page content concludes.
					$minutes = strip_tags($minutes, '<b><i><hr>');
					
					# Start the minutes with the call to order.
					$minutes = stristr($minutes, 'called to order');
					
					# Determine where to end the minutes. We have three versions of this strpos() to
					# accomodation variations in the data, primarily between the house and senate.
					$strpos = strpos($minutes, 'KEY: A');
					if ($strpos == false)
					{
						$strpos = strpos($minutes, 'KEY:  A');
						if ($strpos == false)
						{
							$strpos = strpos($minutes, '<hr>');
						}
					}
					$minutes = substr($minutes, 0, $strpos);
					$minutes = trim($minutes);
					
					# Run the minutes through HTML Purifier, just to make sure they're clean.
					$purifier = new HTMLPurifier();
					$minutes = $purifier->purify($minutes);
					
					# Prepare them for MySQL.
					$minutes = mysql_real_escape_string($minutes);
					
					# If, after all that, we still have any text in these minutes, picking an arbitrary
					# length of 30 characters.
					if (strlen($minutes) > 30)
					{
						# Insert the minutes into the database.
						$sql = 'INSERT INTO minutes
								SET date = "'.$date.'", chamber="'.$chamber.'",
								text="'.$minutes.'"';
						$result = mysql_query($sql);
						if (!$result)
						{
							echo '<p>'.$date.' '.$chamber.' <font color="red">failed</font>.</p>';
						}
						else
						{
							echo '<p>'.$date.' '.$chamber.' succeeded.</p>';
						}
					}
				
					# Unset our variables to prevent them from being reused on the next line.
					unset($regs);
					unset($source_url);
					unset($date);
					unset($minutes);
					unset($done);
					unset($started);
					unset($repeat);
					unset($date);
					unset($strpos);
				}
				
				sleep(1);
			}
		}
		
		# Don't accidentally reuse the HTML for the next chamber.
		unset ($raw_html);
	}
?>
