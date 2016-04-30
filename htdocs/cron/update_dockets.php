<?php

	###
	# Retrieve and Store Dockets
	# 
	# PURPOSE
	# Retrieves the dockets from every planned meeting of the Senate and stores them.
	# 
	# NOTES
	# None.
	# 
	# TODO
	# Add House dockets. Every House committee's docket is available, though not in an obvious
	# location. At <http://leg1.state.va.us/cgi-bin/legp504.exe?111+doc+DOCH01>, for example, are
	# the Agriculture dockets. The format involves updating "111" with the LIS session ID and "H01"
	# with the LIS committee ID ("H" with a left-padded lis_id).
	# 
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once('../includes/settings.inc.php');
	include_once('../includes/functions.inc.php');
	
	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.
	@connect_to_db();
	
	# PAGE CONTENT
	
	# Build up an array of subcommittee IDs.
	$sql = 'SELECT committees.id, committees.lis_id, c2.lis_id AS parent_lis_id, committees.chamber
			FROM committees
			LEFT JOIN committees AS c2
				ON committees.parent_id = c2.id
			WHERE committees.chamber="senate" AND committees.lis_id IS NOT NULL';
	$result = @mysql_query($sql);
	if (@mysql_num_rows($result) == 0)
	{
		exit;
	}
	while ($committee = @mysql_fetch_array($result))
	{
	
		# If this is a subcommittee, pad out its parent ID for use in the URL.
		if (!empty($committee['parent_lis_id']))
		{
			$committee['parent_lis_id'] = str_pad($committee['parent_lis_id'], 2, 0, STR_PAD_LEFT);
			# Specify how much padding to place on the LIS ID for the URL.
			$padding = 3;
		}
		else
		{
			# Specify how much padding to place on the LIS ID for the URL.
			$padding = 2;
		}
		
		# Pad out the LIS ID for use in the URL.
		$committee['lis_id'] = str_pad($committee['lis_id'], $padding, 0, STR_PAD_LEFT);
		
		# We only care about the first letter of the chamber.
		if ($committee['chamber'] == 'senate')
		{
			$committee['chamber'] = 'S';
		}
		elseif ($committee['chamber'] == 'house')
		{
			$committee['chamber'] = 'H';
		}
		$committees[] = $committee;
	}
	
	# Create an array of upcoming dates for which there could plausibly be dockets, going
	# out five days.
	$date = time();
	for ($i=0; $i<6; $i++)
	{
		$date = $date + (60 * 60 * 24);
		$dates[$i]['url'] = date('md', $date);
		$dates[$i]['full'] = date('Y-m-d', $date);
	}
	
	foreach ($committees as $committee)
	{
		foreach ($dates as $date)
		{
			# Connecting to the appropriate docket page.
			if ($committee['chamber'] == 'S')
			{
				# Get the docket for a subcommittee.
				if (!empty($committee['parent_lis_id']))
				{
					$url = 'http://leg1.state.va.us/cgi-bin/legp504.exe?'.SESSION_LIS_ID.'+sub+'
						.$committee['chamber'].$committee['parent_lis_id'].$committee['lis_id'].$date['url'];
					$raw_html = get_content($url);
				}
				
				else
				{
					# Get the docket for a committee.
					// Sometimes -- most of the time -- committee URLs contain a "1" between the LIS ID and the
					// date. But sometimes it's a 2. When the 2 is shown, it's also listed on the LIS site
					// parenthetically after the date (i.e. "January 21, 2010 (2)"). We're only finding dockets
					// with 1s, which means we're missing some unknown minority of dockets.
					$url = 'http://leg1.state.va.us/cgi-bin/legp504.exe?'.SESSION_LIS_ID.'+doc+'
						.$committee['chamber'].$committee['lis_id'].'1'.$date['url'];
					$raw_html = get_content($url);
				}
			}
			
			# If the resulting page is longer than 1,000 bytes and we have a match, iterate through those
			# matches.
			if ((strlen($raw_html) > 1000) && (preg_match_all('#[H|S].[B|J|R].[[:space:]]*([0-9]+)#', $raw_html, $bill)))
			{
				
				# We start by clearing out the old docket data, since we're replacing it with new
				# data. This is necessary to avoid continuing to list bills that were once on the
				# docket, but are no longer. (If we only ever add new bills, then we have no
				# method of deleting old bills.)
				$sql = 'DELETE FROM dockets
						WHERE date="'.$date['full'].'" AND committee_id='.$committee['id'];
				mysql_query($sql);
				
				foreach ($bill[0] as $bill)
				{
					
					# Convert the bills to the simplest form.
					$bill = str_replace('.', '', $bill);
					$bill = str_replace(' ', '', $bill);
					$bill = strtolower($bill);
					
					# Insert the meeting data into the dockets table.
					$sql = 'INSERT INTO dockets
							SET date="'.$date['full'].'", committee_id='.$committee['id'].',
							bill_id =
								(SELECT id
								FROM bills
								WHERE number = "'.$bill.'" AND session_id = '.SESSION_ID.'),
							date_created = now()
							ON DUPLICATE KEY UPDATE id=id';
					mysql_query($sql);
				}
			}
		
			# Sleep for one second to avoid overwhelming LIS' server. This is important -- without
			# this, the server will start rejecting these queries, and rightly so.
			sleep(1);
		}
	}

?>