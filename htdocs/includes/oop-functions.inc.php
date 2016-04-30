<?php

class bill
{
	
	# Take a year and a bill number, return a bill ID.
	function getid($year, $number)
	{
		
		# Make sure we've got the information that we need.
		if (!isset($number) || empty($number))
		{
			return false;
		}
		if (!isset($year) || empty($year))
		{
			return false;
		}
		
		# Check that the data is clean.
		if (strlen($year) != 4)
		{
			return false;
		}
		if (strlen($number) > 7)
		{
			return false;
		}
		$number = strtolower($number);
		
		# Query the DB.
		$sql = 'SELECT bills.id
				FROM bills LEFT JOIN sessions ON bills.session_id=sessions.id
				WHERE bills.number="'.mysql_real_escape_string($number).'"
				AND sessions.year='.mysql_real_escape_string($year);
		$result = mysql_query($sql);
		if (mysql_num_rows($result) < 1)
		{
			return false;
		}
		$bill = mysql_fetch_array($result);
		return $bill['id'];
	}
	
	function info($id)
	{
		# Don't proceed unless we have a bill ID.
		if (!isset($id))
		{
			echo 'Bill is undefined.';
			return false;
		}
		# RETRIEVE THE BILL INFO FROM THE DATABASE
		$sql = 'SELECT bills.id, bills.number, bills.session_id, bills.chamber,
				bills.catch_line, bills.chief_patron_id, bills.summary, bills.summary_hash,
				bills.full_text, bills.notes, bills.status, bills.impact_statement_id,
				bills.date_introduced, bills.outcome, bills.incorporated_into,
				bills.copatrons AS copatron_count, representatives.name AS patron,
				districts.number AS patron_district, sessions.year, sessions.lis_id AS session_lis_id,
				representatives.party AS patron_party, representatives.chamber AS patron_chamber,
				representatives.shortname AS patron_shortname, representatives.place AS patron_place,
				DATE_FORMAT(representatives.date_started, "%Y") AS patron_started,
				representatives.address_district AS patron_address,
				committees.name AS committee, committees.shortname AS committee_shortname,
				committees.chamber AS committee_chamber,
				(
					SELECT translation
					FROM bills_status
					WHERE bill_id=bills.id AND translation IS NOT NULL
					ORDER BY date DESC, id DESC
					LIMIT 1
				) AS status_detail,
				(
					SELECT DATE_FORMAT(date, "%m/%d/%Y")
					FROM bills_status
					WHERE bill_id=bills.id AND translation IS NOT NULL
					ORDER BY date DESC, id DESC
					LIMIT 1
				) AS status_detail_date,
				(
					SELECT number
					FROM bills_full_text
					WHERE bill_id = bills.id
					ORDER BY date_introduced DESC
					LIMIT 1
				) AS version
				FROM bills
				LEFT JOIN sessions
				ON sessions.id=bills.session_id
				LEFT JOIN representatives
				ON representatives.id=bills.chief_patron_id
				LEFT JOIN districts
				ON representatives.district_id=districts.id
				LEFT JOIN committees
				ON bills.last_committee_id=committees.id
				WHERE bills.id='.$id;
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		$bill = mysql_fetch_array($result);
		$bill = array_map('stripslashes', $bill);
		
		# Data conversions
		$bill['word_count'] = str_word_count($bill['full_text']);
		$bill['patron_suffix'] = '('.$bill['patron_party'].'-'.$bill['patron_place'].')';
		if ($bill['patron_chamber'] == 'house')
		{
			$bill['patron_prefix'] = 'Del.';
		}
		elseif ($bill['patron_chamber'] == 'senate')
		{
			$bill['patron_prefix'] = 'Sen.';
		}
		
		# If this bill has any copatrons, we want to gather up all of them and include them in the bill
		# array.
		if ($bill['copatron_count'] > 0)
		{
			$sql = 'SELECT representatives.shortname, representatives.name, representatives.chamber,
					representatives.party, representatives.place, representatives.partisanship
					FROM bills_copatrons LEFT JOIN representatives
					ON bills_copatrons.legislator_id=representatives.id
					WHERE bills_copatrons.bill_id='.$bill['id'].'
					ORDER BY representatives.chamber ASC, representatives.name ASC';
			$bill_result = mysql_query($sql);
			while ($copatron = mysql_fetch_array($bill_result))
			{
				$copatron = array_map('stripslashes', $copatron);			
				if ($copatron['chamber'] == 'house')
				{
					$copatron['prefix'] = 'Del.';
				}
				elseif ($bill['chamber'] == 'senate')
				{
					$copatron['prefix'] = 'Sen.';
				}
				$bill['copatron'][] = $copatron;
			}
		}
		
		# Select all tags from the database.
		$sql = 'SELECT id, tag
				FROM tags
				WHERE bill_id='.$bill['id'];
		$result = mysql_query($sql);
		
		# If there are any tags, display them.
		if (mysql_num_rows($result) > 0)
		{
			while ($tag = mysql_fetch_array($result))
			{
				$tag['tag'] = stripslashes($tag['tag']);
				# Save the tags.
				$bill['tags'][] = $tag['tag'];
			}
		}
	
		# The status history.
		$sql = 'SELECT bills_status.status, bills_status.translation,
				DATE_FORMAT(bills_status.date, "%m/%d/%Y") AS date, bills_status.date AS date_raw,
				bills_status.lis_vote_id, votes.total AS vote_count
				FROM bills_status
				LEFT JOIN votes
				ON bills_status.lis_vote_id = votes.lis_id
				AND bills_status.session_id=votes.session_id
				WHERE bills_status.bill_id = '.$bill['id'].'
				ORDER BY date_raw DESC, bills_status.id DESC';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			# Initialize this array.
			$bill['status_history'] = array();
			# Iterate through the status history.
			while ($status = @mysql_fetch_array($result))
			{
				# Clean it up.
				$status = array_map('stripslashes', $status);
				
				# Append this status data to the status history array.
				$bill['status_history'][] = $status;
				
				# We want to save this status data to use later.
				if (!empty($status['translation']))
				{
					$statuses[] = $status['translation'];
				}
			}
		}
		
		return $bill;
		
	} // function "info"
} // end class "bill"




class comments
{
	# Get all of this bill's comments, whether posted directly or as Photosynthesis comments.
	function retrieve($bill_id)
	{
	
		if (isset($bill_id) || empty($bill_id))
		{
			return false;
		}
		
		# We need to get the summary hash and bill ID to gather comments from identical bills.
		// Calling a function from another class isn't working at all. Clearly something has gone
		// wrong.
		$bill = new bill();
		$bill_info = $bill->info($bill_id);
		
		print_r($bill_info);
		
		return '<p>Summary hash: '.$bill_info['summary_hash'].'<br>
			Session ID: '.$bill_info['session_id'].'</p>';
		
		# Start with directly-posted comments.
		$sql = 'SELECT comments.name, comments.date_created, comments.email, comments.url,
				comments.comment, UNIX_TIMESTAMP(comments.date_created) AS timestamp,
				TIMESTAMPDIFF(SECOND, comments.date_created, CURRENT_TIMESTAMP()) AS seconds_since,
				users.representative_id
				FROM comments LEFT JOIN users ON comments.user_id = users.id
				LEFT JOIN bills ON comments.bill_id=bills.id
				WHERE comments.status="published" AND
				(comments.bill_id='.mysql_real_escape_string($bill_id).'
				OR
					(bills.summary_hash = "'.$bill_info['summary_hash'].'"
					AND bills.session_id='.$bill_info['session_id'].')
				)
				ORDER BY comments.date_created ASC';
		$result = @mysql_query($sql);
		if (@mysql_num_rows($result) > 0)
		{
			while ($comment = @mysql_fetch_array($result))
			{
			
				# Clean up the data.
				$comment = array_map("stripslashes", $comment);
				
				# Convert newlines to paragraphs.
				$comment['comment'] = nl2p($comment['comment']);
				
				# Convert $seconds_since to minutes, hours, days, weeks or months.
				$comment['time_since'] = seconds_to_units($comment['seconds_since']);
				
				# Add this comment to the comments array.
				$comments[$comment{timestamp}] = $comment;
			}
		}
		
		# Get all of this bill's Photosynthesis notes.
		$sql = 'SELECT users.name, dashboard_bills.date_modified, users.email, users.url,
				dashboard_bills.notes AS comment, dashboard_portfolios.hash, dashboard_user_data.organization,
				TIMESTAMPDIFF(SECOND, dashboard_bills.date_modified, CURRENT_TIMESTAMP()) AS seconds_since,
				UNIX_TIMESTAMP(dashboard_bills.date_modified) AS timestamp, users.representative_id
				FROM dashboard_bills LEFT JOIN users ON dashboard_bills.user_id = users.id
				LEFT JOIN dashboard_portfolios ON dashboard_bills.portfolio_id = dashboard_portfolios.id
				LEFT JOIN dashboard_user_data ON dashboard_user_data.user_id = users.id
				WHERE dashboard_bills.bill_id='.$bill['id'].' AND dashboard_bills.notes IS NOT NULL
				ORDER BY date_modified ASC';
		$result = @mysql_query($sql);
		if (@mysql_num_rows($result) > 0)
		{
			while ($comment = @mysql_fetch_array($result))
			{
			
				# Clean up the data.
				$comment = array_map("stripslashes", $comment);
				$comment['comment'] = nl2p($comment['comment']);
				
				# Convert $seconds_since to minutes, hours, days, weeks or months.
				$comment['time_since'] = seconds_to_units($comment['seconds_since']);
				
				# Display the organization, if the portfolio is owned by one. Otherwise, display the
				# user's name.
				if (!empty($comment['organization']))
				{
					$comment['name'] = $comment['organization'];
				}
				else
				{
					# Make the user closer to anonymous.
					$tmp = explode(' ', $comment['name']);
					if (count($tmp) > 1)
					{
						$comment['name'] = $tmp[0].' '.$tmp[1]{0}.'.';
					}
					else
					{
						$comment['name'] = $tmp[0];
					}
				}
				
				# Mark this as being a Photosynthesis.
				$comment['type'] = 'photosynthesis';
				
				# Add this comment to the comments array.
				$comments[$comment{timestamp}] = $comment;
			}
		}
	}
	
} // end class "comments"

?>
