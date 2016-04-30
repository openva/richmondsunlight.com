<?php

	###
	# Retrieve and Store the Meeting Schedule
	# 
	# PURPOSE
	# Retrieves the CSV file of upcoming meetings, parses it, and stores it in the database.
	# 
	# NOTES
	# This has an odd three-step process. First we retrieve the HTML page. Then we use the link to
	# the CSV within there to retrieve a dynamic redirect page. Then we use a link within there to
	# retrieve the actual CSV. After those steps comes the slicing, dicing, and inserting.
	# 
	# TODO
	# Deal with the problem of duplicates that result from events changing after they've been
	# syndicated. We have no process for updating existing events, only for adding unrecognized
	# ones.
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
	
	# Build up an array of committee names and IDs, which we'll use later on to match the calendar
	# data.
	$sql = 'SELECT c1.id, c1.lis_id, c2.name AS parent, c1.name, c1.chamber
			FROM committees AS c1
			LEFT JOIN committees AS c2
				ON c1.parent_id=c2.id
			ORDER BY c1.chamber, c2.name, c1.name';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{
		exit;
	}
	while ($committee = mysql_fetch_array($result))
	{
		
		$committee = array_map('stripslashes', $committee);
		
		# If this is a subcommittee, shuffle around the array keys.
		if (!empty($committee['parent']))
		{
			$committee['sub'] = $committee['name'];
			$committee['name'] = $committee['parent'];
			unset($committee['parent']);
		}
		
		# Begin to establish the plain text description that we'll use to try to match the
		# meeting description.
		$tmp = ucfirst($committee['chamber']).' '.$committee['name'];
		
		# If this is a subcommittee, then we have to deal with a series of naming possibilities,
		# since legislative staff are hugely inconsistent in their naming practices. Any of the
		# following is viable:
		# Senate Finance Education Subcommittee
		# Senate Finance - Education
		# Senate Finance - Subcommittee Education
		if (!empty($committee['sub']))
		{
			$committees[] = array($committee['id'] => $tmp.' - '.$committee['sub']);
			$committees[] = array($committee['id'] => $tmp.' - Subcommittee '.$committee['sub']);
			$committees[] = array($committee['id'] => $tmp.' '.$committee['sub'].' Subcommittee');
			
			# If the word "and" is used in this subcommittee name, then we need to also create
			# versions of it with an ampersand in place of the word "and," because LIS can't decide
			# which they want to use to name committees.
			if (stristr($committee['sub'], ' and ') != false)
			{
				$committee['sub'] = str_replace(' and ', ' & ', $committee['sub']);
				$committees[] = array($committee['id'] => $tmp.' - '.$committee['sub']);
				$committees[] = array($committee['id'] => $tmp.' - Subcommittee '.$committee['sub']);
				$committees[] = array($committee['id'] => $tmp.' '.$committee['sub'].' Subcommittee');
			}
		}
		else
		{
			$committees[] = array($committee['id'] => $tmp);
			if (stristr($tmp, ' and ') != false)
			{
				$tmp = str_replace(' and ', ' & ', $tmp);
				$committees[] = array($committee['id'] => $tmp);
			}
		}
		
		unset($tmp);
	}
	
	# And build up a listing of all meetings being held after now, which we'll use below to avoid
	# making duplicate additions.
	// Since we're separating out the date and time fields, it's not clear to me how "now" is
	// established. Maybe we just select everything from today on, and filter out the gone-by events
	// in the PHP?
	$sql = 'SELECT committee_id, date, time, timedesc, description, location
			FROM meetings
			WHERE session_id='.SESSION_ID.' AND date >= now()';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0)
	{
		$upcoming = array();
		while ($tmp = mysql_fetch_array($result))
		{
			$tmp = array_map('stripslashes', $tmp);
			$upcoming[] = $tmp;
		}
	}
	
	# Retrieve the HTML for the schedule.
	$html = get_content('http://leg1.state.va.us/cgi-bin/legp504.exe?'.SESSION_LIS_ID.'+oth+MTG');
	
	# Extract the redirection URL.
	eregi('<a href="/cgi-bin/legp507.exe\?([0-9]{3})\+([a-z0-9]{3})">csv file</a>', $html, $regs);
	if (!isset($regs) || !is_array($regs))
	{
		exit;
	}
	
	# Fetch the redirection page. The legislature doesn't just link to the URL, because that might
	# make things easier. Instead they provide a redirection page, with a URL for the CSV provided
	# dynamically--it changes every time.
	$redirect = get_content('http://leg1.state.va.us/cgi-bin/legp507.exe?'.$regs[1].'+'.$regs[2]);
	unset($regs);
	
	# Extract the CSV URL.
	eregi('<a href="(.*)">here</a>', $redirect, $regs);
	if (!isset($regs) || !is_array($regs))
	{
		exit;
	}
	
	# Fetch the CSV.
	$csv = get_content($regs[1]);
	
	# Eliminate any blank lines at the end.
	$csv = trim($csv);
	
	# Break it up into an array.
	$csv = explode("\r", $csv);
	
	# Eliminate the column heads.
	unset($csv[0]);
	
	# Iterate through the lines.
	foreach ($csv as &$meeting)
	{
	
		# Strip out any carriage returns lying around.
		$meeting = str_replace("\r", '', $meeting);
		
		# Turn the CSV into an array.
		$meeting = explode('","', $meeting);
		
		# Trim every column of its whitespace.
		foreach ($meeting as &$column)
		{
			$column = trim($column);
		}
		
		# Name each element in the array.
		$meeting['date'] = $meeting[0];
		$meeting['time'] = $meeting[1];
		$meeting['description'] = $meeting[3];
		
		# Unset every numbered element.
		unset($meeting[0]);
		unset($meeting[1]);
		unset($meeting[2]);
		unset($meeting[3]);
		
		# Eliminate the quotation marks left over from the CSV.
		$meeting['date'] = str_replace('"', '', $meeting['date']);
		$meeting['description'] = str_replace('"', '', $meeting['description']);
		
		# Determine which chamber that this meeting pertains to.
		if (eregi('house', $meeting['description']) !== true)
		{
			$meeting['chamber'] = 'house';
		}
		elseif (eregi('senate', $meeting['description']) !== true)
		{
			$meeting['chamber'] = 'senate';
		}
		else
		{
			continue;
		}
		
		# This notice is to point out that a committee isn't meeting, that the senate has adjourned,
		# or that the house has adjourned, then we can safely ignore it.
		if (
				stristr($meeting['description'], 'not meeting')
				|| stristr($meeting['description'], 'Senate Adjourned')
				||stristr($meeting['description'], 'House Adjourned')
			)
		{
			continue;
		}
		
		# If an approximate time is listed in the committee information (something like "1/2 hr aft"
		# or "TBA"), then we've got to a) turn it into plain English and b) ignore the claimed time.
		if (
				stristr($meeting['description'], '1/2 hr aft')
				||
				stristr($meeting['description'], '1/2 hour after')
			)
		{
			$meeting['timedesc'] = 'Half an hour after the '.ucfirst($meeting['chamber'])
				.' adjourns';
			unset($meeting['time']);
		}
		elseif (stristr($meeting['description'], '1 and 1/2 hours after'))
		{
			$meeting['timedesc'] = 'An hour and a half after the '.ucfirst($meeting['chamber'])
				.' adjourns';
			unset($meeting['time']);
		}
		elseif (stristr($meeting['description'], '1/2 hour before Session'))
		{
			$meeting['timedesc'] = 'Half an hour before the '.ucfirst($meeting['chamber'])
				.' convenes';
			unset($meeting['time']);
		}
		elseif (stristr($meeting['description'], 'TBA'))
		{
			$meeting['timedesc'] = 'To be announced';
			unset($meeting['time']);
		}
		
		# If the time is approximate, then we want to establish a meeting date. (But not a time.)
		if (isset($meeting['timedesc']))
		{
			# Establish a meeting date.
			$meeting['datetime'] = strtotime('00:00:00 '.$meeting['date']);
			$meeting['date'] = date('Y-m-d', $meeting['datetime']);
			unset($meeting['time']);
			unset($meeting['datetime']);
		}
		
		# But if we've got a trustworthy time, format the date and the time properly.
		else
		{
			# Convert the date and time into a timestamp.
			$meeting['datetime'] = strtotime($meeting['time'].' '.$meeting['date']);
			$meeting['date'] = date('Y-m-d', $meeting['datetime']);
			$meeting['time'] = date('H:i', $meeting['datetime']).':00';
			unset($meeting['datetime']);
		}
		
		# Clean up the meeting description
		$tmp = explode(';', $meeting['description']);
		if (strstr($tmp[count($tmp)-1], '-'))
		{
			$tmp2 = explode('-', $tmp[count($tmp)-1]);
			$tmp[count($tmp)-1] = $tmp2[0];
		}
		$meeting['description'] = trim($tmp[0]);
		$meeting['location'] = trim($tmp[1]);
		
		# Attempt to match the committee with a known committee. Start by stepping through every
		# committee.
		for ($i=0; $i<count($committees); $i++)
		{
			
			# Since each committee can have multiple names, we now step through each name for this
			# committee and try to match it.
			foreach ($committees[$i] as $id => $committee)
			{
				if (stristr($meeting['description'], $committee) != false)
				{
					$meeting['committee_id'] = $id;
					break;
				}
			}
		}
		
		// check to see if we already know about this meeting by comparing it to data in the DB
		
		# If this meeting has gone by, then we can safely ignore it.
		if (isset($meeting['time']))
		{
			$tmp = strtotime($meeting['date'].' '.$meeting['time']);
		}
		else
		{
			$tmp = strtotime($meeting['date'].' 00:00:00');
		}
		if ($tmp < time())
		{
			continue;
		}
		
		# If we've already got a record of this meeting then, again, we can safely ignore it.
		if (!empty($upcoming))
		{
			foreach ($upcoming as $known)
			{
				if	(
						($meeting['date'] == $known['date'])
						&&
						($meeting['description'] == $known['description'])
						&&
						(
							($meeting['location'] == $known['location'])
							||
							($meeting['committee_id'] == $known['committee_id'])
						)
						&&
						(
							($meeting['time'] == $known['time'])
							||
							($meeting['timedesc'] == $known['timedesc'])
						)
					)
				{
					$duplicate = true;
					break;
				}
			}
			if ($duplicate == true)
			{
				unset($duplicate);
				continue;
			}
		}
		
		# Prepare and insert the data into the DB.
		$meeting = array_map('mysql_real_escape_string', $meeting);
		$sql = 'INSERT INTO meetings
				SET date="'.$meeting['date'].'", description="'.$meeting['description'].'",
				session_id='.SESSION_ID.', location="'.$meeting['location'].'",
				date_created=now()';
		if (!empty($meeting['time']))
		{
			$sql .= ', time="'.$meeting['time'].'"';
		}
		if (!empty($meeting['timedesc']))
		{
			$sql .= ', timedesc="'.$meeting['timedesc'].'"';
		}
		if (!empty($meeting['committee_id']))
		{
			$sql .= ', committee_id='.$meeting['committee_id'].'';
		}
		$result = mysql_query($sql);
		if (mysql_affected_rows($result) == 0)
		{
			echo '<p style="color: #f00;">Insertion of '.$meeting['description'].' on
				'.$meeting['date'].' failed.</p>';
		}
		else
		{
			echo '<p>Added '.$meeting['description'].' on '.$meeting['date'].'</p>';
		}
	}
	
	# Delete all of the duplicate meetings. We end up with the same meeting recorded over and over
	# again, and that's most easily dealt with by simply deleting them after they're inserted.
	$sql = 'DELETE m1
			FROM meetings m1, meetings m2
			WHERE m1.date=m2.date AND m1.time=m2.time AND m1.location=m2.location
			AND m1.description=m2.description AND m1.id < m2.id';
	mysql_query($sql);

?>
