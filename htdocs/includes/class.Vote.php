<?php

class Vote
{
	
	# Take an LIS ID, return a vote tally.
	function get_aggregate($lis_id, $session_id)
	{
		
		# Make sure we've got the information that we need.
		if (!isset($lis_id) || empty($lis_id))
		{
			return FALSE;
		}

		if (!isset($session_id) || empty($session_id))
		{
			return FALSE;
		}
		
		# Check that the data is clean.
		if (strlen($lis_id) > 12)
		{
			return FALSE;
		}
		if (strlen($session_id) > 3)
		{
			return FALSE;
		}
		
		/*
		 * Query the DB.
		 */
		$sql = 'SELECT chamber, outcome, tally
				FROM votes
				WHERE lis_id="' . $lis_id . '"
				AND session_id = ' . $session_id;
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			die('No such vote found.');
		}

		$vote = mysql_fetch_assoc($result);
		$vote = array_map('stripslashes', $vote);
		return $vote;
		
	}

	function get_detailed($lis_id, $session_id)
	{
		
		# Make sure we've got the information that we need.
		if (!isset($lis_id) || empty($lis_id))
		{
			return FALSE;
		}

		if (!isset($session_id) || empty($session_id))
		{
			return FALSE;
		}
		
		# Check that the data is clean.
		if (strlen($lis_id) > 12)
		{
			return FALSE;
		}
		if (strlen($session_id) > 3)
		{
			return FALSE;
		}

		// The following bit was commented out of the WHERE portion of this query:
		//
		// AND votes.session_id='.$bill['session_id'].'
		//
		// When bills survive until the following session, and then are voted on anew, they're odd,
		// because they exist twice in Richmond Sunlight. So we can't make the query unique by session
		// ID. OTOH, if LIS vote IDs aren't unique, this may prove to be problematic.
		$sql = 'SELECT representatives.name, representatives.shortname,
				representatives_votes.vote, representatives.party,
				representatives.chamber, representatives.address_district AS address,
				DATE_FORMAT(representatives.date_started, "%Y") AS started,
				districts.number AS district
				FROM votes
				LEFT JOIN representatives_votes
					ON votes.id = representatives_votes.vote_id
				LEFT JOIN representatives
					ON representatives_votes.representative_id = representatives.id
				LEFT JOIN districts
					ON representatives.district_id=districts.id
				WHERE votes.lis_id="'.$lis_id.'" AND votes.session_id="' . $session_id . '"
				ORDER BY vote ASC, name ASC';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) < 1)
		{
			return FALSE;
		}

		# Store all of the resulting data in an array, since we have to reuse it a couple of times.
		$legislators = array();
		while ($legislator = mysql_fetch_assoc($result))
		{
			$legislators[] = $legislator;
		}

		return $legislators;

	}
	
}
