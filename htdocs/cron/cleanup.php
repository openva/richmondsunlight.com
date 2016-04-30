<?php

###
# MISC. DATA CLEAN UP FUNCTIONS
# By Waldo Jaquith <waldo@jaquith.org>
# 12/04/2008
#
# PURPOSE
# Does all of the nice conversions of data that make Richmond Sunlight something other than a
# parroting of LIS data. This was actually written over the course of years--the above date is just
# when it was forked off of update_db.php and put into its own file.
#
# NOTES
# This won't work if called on its own--it will only function when invoked from within
# update_db.php.
#
###


###
# UPDATE BILLS' INTRODUCTION DATE
# Perform a single query to provide the introduction date for each bill, based
# on the date field within each bill' legislative history.  This only works if
# HISTORY.CSV has been republished since this bill appeared.  So we do something
# really inefficient, which is that we update not just where date_introduced
# is null, but we update *all* bills.  That way we compensate for the
# possibility of having previously made incorrect assumptions about the date
# introduced (see next query) and correct them.
###
$sql = $dbh->prepare('UPDATE bills
						SET date_introduced = 
							(SELECT date
							FROM bills_status
							WHERE bills_status.bill_id = bills.id
							ORDER BY date ASC
							LIMIT 1)
						WHERE session_id= :session_id
						AND date_introduced IS NULL');
$sql->bindParam(':session_id', $session_id);
$result = $sql->execute();

###
# MAKE ANOTHER ATTEMPT TO UPDATE BILLS' INTRODUCTION DATE
# During session, new bills are filed and syndicated via BILLS.CSV prior to
# the availability of detailed information via the daily-published HISTORY.CSV.
# To compensate for this, we simply set null introduction dates to equal today's
# date.  The hope is that, for any of these that are inaccurate, they'll be
# overridden by the HISTORY.CSV data down the line.  The reason that we do this
# is that much of the site depends on having an introduction date.  We can't
# make people wait 24 hours to see what's been introduced, so it makes more
# sense to meet that minimum data level and move on.
###
$sql = $dbh->prepare('UPDATE bills
						SET date_introduced = DATE_FORMAT( date_created, "%Y-%m-%d" ) 
						WHERE date_introduced IS NULL
						AND session_id= :session_id');
$sql->bindParam(':session_id', $session_id);
$result = $sql->execute();

###
# UPDATE BILLS' STATUS
# Perform a series of queries to provide an indication of whether the bill passed
# or failed in committee.  The only purpose of this is to determine whether to
# check off the box next to "Passed Committee" in the "how a bill becomes law"
# series on every bill's page.
###

# Specify which subcommittee that a bill is in.
/*$sql = 'SELECT id, status
		FROM bills_status
		WHERE status LIKE "Assigned to%sub%"
		AND (translation IS NULL OR translation = "assigned to subcommittee")';
$result = mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{
	while ($status = @mysql_fetch_array($result))
	{
		
	}
}*/


$sql = $dbh->prepare('UPDATE bills_status
						SET translation="introduced"
						WHERE status LIKE "Prefiled %" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="in subcommittee"
						WHERE status LIKE "Assigned%sub%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="in committee"
						WHERE status LIKE "Referred to Committee%" AND translation IS NULL
						AND session_id = :session_id');
$sql->bindParam(':session_id', $session_id);
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="in committee"
						WHERE status = "Committee" AND translation IS NULL
						AND session_id = :session_id');
$sql->bindParam(':session_id', $session_id);
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="in committee"
						WHERE status = "Rereferred to%" AND translation IS NULL
						AND session_id = :session_id');
$sql->bindParam(':session_id', $session_id);
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed committee"
						WHERE status LIKE "Left in %" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed committee"
						WHERE status LIKE "Tabled in %" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed committee"
						WHERE (status LIKE "Passed by in %"
						OR status LIKE "Passed by indefinitely%")
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
					SET translation="failed committee"
					WHERE status LIKE "Failed to report%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed committee"
						WHERE status LIKE "Continued to :session_year%"
						AND translation IS NULL');
$next_year = $session_year + 1;
$sql->bindParam(':session_year', $next_year);
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed committee"
						WHERE status LIKE "Stricken from docket%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed subcommittee"
						WHERE (status LIKE "Subcommittee recommends passing by indefinitely%"
						OR status LIKE "Subcommittee recommends laying on the table"
						OR status LIKE "Subcommittee failed to recommend reporting%"
						OR status LIKE "Subcommittee recommends striking from the docket%")
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="passed subcommittee"
						WHERE status LIKE "Subcommittee recommends reporting%"
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="incorporated"
						WHERE status LIKE "Incorporated%"
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="stricken"
						WHERE status LIKE "Stricken at request of patron%"
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="passed committee"
						WHERE (status LIKE "Reported from %" OR status LIKE "Discharged from %"
							OR status LIKE "Rereferred from %")
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="passed house"
						WHERE (status LIKE "%and passed House%" OR status LIKE "Agreed to by House%"
							OR status LIKE "Passed House%")
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed house"
						WHERE status LIKE "%engrossment refused by House%"
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed house"
						WHERE status LIKE "Defeated by House%"
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="passed senate"
						WHERE (status LIKE "%and passed Senate%" OR status LIKE "Agreed to by Senate%"
							OR status LIKE "Passed Senate%")
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="failed senate"
						WHERE (status LIKE "Failed to pass in Senate" OR status LIKE "%defeated by Senate%")
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="enacted"
						WHERE status LIKE "Enacted%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="signed by governor"
						WHERE status LIKE "%Approved by Governor%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="signed by governor"
						WHERE status LIKE "%Governor\'s recommendation adopted%"
						AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="vetoed by governor"
						WHERE status LIKE "%Vetoed by Governor%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="passed"
						WHERE status LIKE "%Signed by President%" AND translation IS NULL');
$result = $sql->execute();

$sql = $dbh->prepare('UPDATE bills_status
						SET translation="enacted"
						WHERE status LIKE "%veto overridden%" AND translation IS NULL');
$result = $sql->execute();

# UPDATE BILLS TABLE STATUS FROM STATUS TABLE
$sql = $dbh->prepare('UPDATE bills
						SET status =
							(SELECT translation
							FROM bills_status
							WHERE bills_status.bill_id = bills.id AND translation IS NOT NULL
							ORDER BY date DESC, id DESC
							LIMIT 1)
						WHERE session_id=:session_id');
$sql->bindParam(':session_id', $session_id);
$result = $sql->execute();

###
# UPDATE BILLS' IMPACT STATEMENTS
# Perform a series of queries to flag all bills for which an impact statement has been
# provided.  By setting that flag, bill.php then knows to provide a link to read the
# bill's impact statement.
###
$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=158
		WHERE bills_status.status LIKE "Impact statement from VRS%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=171
		WHERE bills_status.status LIKE "Impact statement from SCC%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=122
		WHERE bills_status.status LIKE "Impact statement from DPB%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=161
		WHERE bills_status.status LIKE "Impact statement from TAX%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=160
		WHERE bills_status.status LIKE "Impact statement from VCSC%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=999
		WHERE bills_status.status LIKE "Impact statement from ABC%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=154
		WHERE bills_status.status LIKE "Impact statement from DMV%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=501
		WHERE bills_status.status LIKE "Impact statement from VDOT%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=160
		WHERE bills_status.status LIKE "Impact statement from VCSC%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

$sql = 'UPDATE bills
		LEFT JOIN bills_status
		ON bills.id=bills_status.bill_id
		SET bills.impact_statement_id=156
		WHERE bills_status.status LIKE "Impact statement from VSP%" AND bills.impact_statement_id IS NULL';
mysql_query($sql);

###
# UPDATE BILLS' STATUS
# Newly-introduced bills are, oddly, not having their status updated.
# Set them to the status of "introduced."
###
$sql = 'UPDATE bills
		SET status="introduced"
		WHERE (status IS NULL OR status="") AND session_id='.$session_id;
$result = mysql_query($sql);

###
# UPDATE BILLS' OUTCOME FLAG
# Every bill has a passed/failed flag, used for sweeping DB queries where we only want bills that
# have passed (or, I suppose, that have failed).
###
$sql = 'UPDATE bills
		SET outcome="failed"
		WHERE (status="failed committee" OR status="failed" OR status="failed house"
			OR status="failed senate" OR status="vetoed by governor" OR status="stricken")
		AND (outcome IS NULL OR outcome="passed") AND session_id='.$session_id;
mysql_query($sql);

$sql = 'UPDATE bills
		SET outcome="passed"
		WHERE (status="signed by governor" OR status="enacted")
		AND (outcome IS NULL OR outcome="failed") AND session_id='.$session_id;
mysql_query($sql);

$sql = 'UPDATE bills
		SET outcome = NULL
		WHERE outcome="failed" AND status != "failed committee" AND status != "failed" AND
		status != "failed house"
		AND status != "failed senate" AND status != "vetoed by governor" AND status != "stricken"
		AND status != "signed by governor" AND status != "enacted" AND session_id='.$session_id;
mysql_query($sql);

###
# UPDATE BILLS' FULL TEXT WHERE IT'S CURRENTLY BLANK
# Synchronize the bills table's bill text with the text stored in the bills_full_text table.
# The latter stores the latest text but, for convenience of querying, we sync it here.
###
$sql = 'UPDATE bills
		SET full_text =
			(SELECT text
			FROM bills_full_text
			WHERE bill_id = bills.id AND text IS NOT NULL
			ORDER BY date_introduced DESC
			LIMIT 1)
		WHERE session_id='.$session_id.' AND full_text IS NULL';
mysql_query($sql);

###
# PERIODICALLY SYNCHRONIZE BILLS' FULL TEXT
# Synchronize the bills table's bill text with the text stored in the bills_full_text table.
# The latter stores the latest text but, for convenience of querying, we synch it here. This is
# simply because the full text changes periodically, and we want to make sure that we're working
# with the most recent version.
###
$sql = 'UPDATE bills
		SET full_text =
			(SELECT text
			FROM bills_full_text
			WHERE bill_id = bills.id
			ORDER BY date_introduced DESC
			LIMIT 1)
		WHERE session_id='.$session_id.'
		ORDER BY RAND()
		LIMIT 50';
mysql_query($sql);

###
# CLEAN UP STRAY CONTROL CHARACTERS
# From the process of converting from the Western to UTF-8 character set (leg1 stores data in a
# Western mish-mash), some goofy characters tend to show up.
###
$sql = 'UPDATE bills
		SET summary = REPLACE(summary, "Â", "")';
mysql_query($sql);

###
# UPDATE BILLS' SUMMARY HASHES
# We maintain an MD5 hash of all bill summaries to allow a quick discovery of bills that are
# identical to the current one.
###
$sql = 'UPDATE bills
		SET summary_hash = md5(summary)
		WHERE summary_hash IS NULL';
mysql_query($sql);

###
# MARK BILLS AS INCORPORATED INTO ANOTHER BILL
# Sometimes a bill is absorbed into another bill, generally in committee. When that happens we want
# to record the ID of the target bill in the source bill. For instance, if HB1 is incorporated into
# HB2, then we record HB2's ID (an integer) in the incorporated_into column for the HB1 record.
###
$sql = 'SELECT id, session_id, (
			SELECT status
			FROM bills_status
			WHERE bill_id = bills.id
			AND translation = "incorporated"
			LIMIT 1
			) AS incorporated
		FROM bills
		WHERE incorporated_into IS NULL
		HAVING incorporated IS NOT NULL';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	while ($bill = mysql_fetch_array($result))
	{
		$bill = array_map('stripslashes', $bill);
		
		# Extract the target bill number from the incorporation text.
		preg_match('/(hb|sb|hr|sr|hjr|sjr)([0-9]+)/', $bill['incorporated'], $regs);
		if (isset($regs[0]))
		{
			$bill['incorporated_into'] = strtolower($regs[0]);
		}
		
		# If we successfully got a target bill number, then we can update the source bill with
		# its ID.
		if (!empty($bill['incorporated_into']))
		{
			$sql = 'SELECT id
					FROM bills
					WHERE number="'.$bill['incorporated_into'].'"
					AND session_id='.$bill['session_id'];
			$result2 = mysql_query($sql);
			if ($result2 !== false)
			{
				$tmp = mysql_fetch_array($result2);
				$bill['incorporated_into'] = $tmp['id'];
	
				$sql = 'UPDATE bills
						SET incorporated_into = '.$bill['incorporated_into'].'
						WHERE id='.$bill['id'];
				$result2 = mysql_query($sql);
			}
		}
	}
}

###
# SYNCHRONIZE IDENTICAL BILLS' TAGS
# When the summary hashes for two or more bills are identical, then we can safely synchronize their
# tags.
###
$sql = 'SELECT bills.summary_hash AS hash, bills.id,
			GROUP_CONCAT( tags.tag
			ORDER BY tag ASC 
			SEPARATOR "," ) AS tags
		FROM bills LEFT JOIN tags
		ON bills.id = tags.bill_id
		LEFT JOIN bills AS bills2
		ON bills.summary_hash=bills2.summary_hash
		WHERE bills.id != bills2.id
		GROUP BY bills.id
		ORDER BY bills.summary_hash';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	# Initialize the array.
	$hash = array();
	
	# Iterate through the bills and build up an array of them.
	while ($bill = mysql_fetch_array($result))
	{
		if (!empty($bill['tags']))
		{
			$tags = explode(',',$bill['tags']);
			$hash[$bill{'hash'}][$bill{'id'}] = $tags;
		}
		else
		{
			$hash[$bill{'hash'}][$bill{'id'}] = array();
		}
	}
	
	# Iterate through each grouping of identical bills.
	foreach ($hash as $bill)
	{
		
		// This sucks, because it's only comparing the first two bills in the array. But we've got
		// to, because the rest of this is dependent on that construct. At some point this will
		// really need to be rewritten to deal with this problem.
		if (count($bill) > 2)
		{
			for ($i=0; $i<count($bill); $i++)
			{
				unset($bill[$i]);
			}
		}
		
		# It's possible for bills to slip through that are not, in fact, identical to others. That's
		# a problem in the SQL query, but as a double check, we avoid that sort of thing here.
		elseif (count($bill) == 1)
		{
			continue;
		}
	
		# Save the keys to this array, because we'll need them later.
		$keys = array_keys($bill);
		
		# Convert this from an associative array to an indexed array.
		$bill = array_values($bill);
		
		# If neither bill has any tags, we might as well stop now.
		if ((count($bill[0]) == 0) && (count($bill[1]) == 0))
		{
			unset($hash);
			continue;
		}
		
		else
		{
			
			# Check if there's any difference between these two tag sets. You'd think that we only
			# need to do a single array diff, rather than alternating. But you'd be wrong.
			$tmp = array_diff($bill[0], $bill[1]);
			$tmp2 = array_diff($bill[1], $bill[0]);
			$diff = array_merge($tmp, $tmp2);
			
			# If there's any difference between the tags, then we need to synch them.
			if (count($diff) > 0)
			{
				
				# If this is a simple case of one bill having tags and the other one not, just
				# assign the tags to the other bill and be done with it.
				if ((count($bill[0]) == 0) && (count($bill[1]) > 0))
				{
					$bill[0] = array_merge($bill[0], $bill[1]);
					$bill[1] = array();
				}
				elseif ((count($bill[0]) == 0) && (count($bill[1]) > 0))
				{
					$bill[1] = array_merge($bill[0], $bill[1]);
					$bill[0] = array();
				}
				
				# But if both bills are tagged, but not identically, then we need to join
				# them.
				else
				{
					
					# Save a copy of the original bill tags; we'll need them later.
					$original = $bill;
					
					# Merge the values together.
					$merge = array_merge($bill[0], $bill[1]);
					
					# Calculate the intersection between these two bills' tags.
					$intersection = array_intersect($bill[0], $bill[1]);
					
					# Now unset any tag that's already present in both bills.
					$bill[0] = array_diff($bill[0], $intersection);
					$bill[1] = array_diff($bill[1], $intersection);
					
					# Make each bill's tag the product of the two of them.
					$bill[0] = array_merge($bill[0], $bill[1]);
					$bill[1] = array_merge($bill[0], $bill[1]);
					
					# Unset any tag that was present initially.
					$bill[0] = array_diff($bill[0], $original[0]);
					$bill[1] = array_diff($bill[1], $original[1]);
					
					# Just in case, make sure that these are unique.
					$bill[0] = array_unique($bill[0]);
					$bill[1] = array_unique($bill[1]);
				}
				
				# Convert this indexed array back to an associative array by restoring its original
				# keys.
				for ($i=0; $i<count($keys); $i++)
				{
					$bill[$keys{$i}] = $bill[$i];
					unset($bill[$i]);
				}
			}
		
			# If there's no difference between the tags, we can give up.
			else
			{
				unset($hash);
				continue;
			}
			
			# Iterate through the bills.
			foreach ($bill as $bill_id => $tags)
			{
				# Iterate through the tags, but only if there are any.
				if (count($tags > 0))
				{
					foreach ($tags as $tag)
					{
						# Assemble the insertion SQL. The tags table assumes that we'll always have a user id, 
						# but that's not true here. So we employ a user ID of 0.
						$sql = 'INSERT INTO tags
								SET bill_id='.$bill_id.', tag="'.$tag.'",
								user_id=0, date_created=now()';
						mysql_query($sql);
					}
				}
			}		
		}
	}	
}


###
# UPDATE BILLS' HOTNESS RANKING
# Each bill is ranked by how "hot" it is. This is a metric for how interested that people are in
# a given bill. When the legislature is in session, we base this on the past day's activity. When
# it's not in session, we based it on the past three days. The formula itself is based on five
# factors: comments, views, Photosynthesis adds, poll votes, and subscriptions to comments.
###
if (IN_SESSION == 'y')
{
	$days = 1;
}
else
{
	$days = 3;
}
$sql = 'UPDATE bills
		SET hotness=
			(
				(SELECT COUNT(*)
				FROM comments
				WHERE bill_id=bills.id AND (DATEDIFF(now(), date_created) <= '.$days.')) * 5
				+
				(SELECT COUNT(DISTINCT ip)
				FROM bills_views
				WHERE bill_id=bills.id AND (DATEDIFF(now(), date) <= '.$days.')) / 3
				+
				(SELECT COUNT(*)
				FROM dashboard_bills
				WHERE bill_id=bills.id AND (DATEDIFF(now(), date_created) <= '.$days.')) * 10
				+
				(SELECT COUNT(*)
				FROM polls
				WHERE bill_id=bills.id AND (DATEDIFF(now(), date_created) <= '.$days.')) * 2
				+
				(SELECT COUNT(*)
				FROM comments_subscriptions
				WHERE bill_id=bills.id AND (DATEDIFF(now(), date_created) <= '.$days.')) * 2
			)
		WHERE session_id='.$session_id;
mysql_query($sql);

###
# UPDATE BILLS' INTRESTINGNESS RANKING
# Each bill is ranked by how interesting it is. This is a metric for how interested that people are
# in a given bill. This is based on the entirety of the data that we have on this bill. (Compare
# this with "hotness," which is based on recent interest.) The formula itself is based on five
# factors: comments, views, Photosynthesis adds, poll votes, and subscriptions to comments.
###
$sql = 'UPDATE bills
		SET interestingness=
			(
				(SELECT COUNT(*)
				FROM comments
				WHERE bill_id=bills.id) * 5
				+
				(SELECT COUNT(DISTINCT ip)
				FROM bills_views
				WHERE bill_id=bills.id) / 3
				+
				(SELECT COUNT(*)
				FROM dashboard_bills
				WHERE bill_id=bills.id) * 10
				+
				(SELECT COUNT(*)
				FROM polls
				WHERE bill_id=bills.id) * 2
				+
				(SELECT COUNT(*)
				FROM comments_subscriptions
				WHERE bill_id=bills.id) * 2
			)
		WHERE session_id='.$session_id;
mysql_query($sql);


###
# UPDATE BILLS' COPATRON COUNT
# The bill stores the number of copatrons that it has. Update that number by refreshing it from the
# bills_copatrons table. We don't bother with limiting it to the current session because, hey, it
# won't hurt.
###
$sql = 'UPDATE bills
		SET copatrons =
			(SELECT COUNT(*)
			FROM bills_copatrons
			WHERE bill_id=bills.id)';
mysql_query($sql);

###
# UPDATE VOTES CONTENTION RANKING
# Calculate how contested that each vote was, on a 0-1 scale. Zero means that it passed unanimously,
# while one means that it was a 50/50 vote.
###

# Generate a listing of every vote that doesn't have a contested rating.
$sql = 'SELECT id, tally
		FROM votes
		WHERE contested IS NULL';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	while ($vote = mysql_fetch_array($result))
	{
		
		# If the vote is more than XX-YY (such as XX-YY-ZZ, or XX-YY-ZZ-AA), then we hack off
		# everything after XX-YY.
		if (substr_count($vote['tally'], '-') > 1)
		{
			# Disassemble the tally and hack off any final bit.
			$tmp = explode('-', $vote['tally']);
			$tmp = array_slice($tmp, 0, 2);
			
			# We get the vote total from adding up the results of the Ys and the Ns. Though the
			# total number of votes cast is stored in the database, that includes the absentions
			# and absent votes, which isn't helpful here.
			$vote['total'] = array_sum($tmp);
			
			# Put the tally back together.
			$vote['tally'] = implode('-', $tmp);
			
		}
		
		# If either the first (Ys) or second (Ns) element of that temporary array is a zero, then
		# we know that this vote wasn't contested at all. Also, we know the same thing if the vote
		# total is zero. One would think that the first two conditions would obviate the third,
		# but, oddly, that's not so in practice.
		if (($tmp[0] == 0) || ($tmp[1] == 0) || ($vote['total'] == 0))
		{
			$vote['contested'] = 0;
		}
		
		# If the vote was contested, then proceed to do the math to determine how contested that it
		# was.
		else
		{
			# We need to eval() the tally in order to treat the string like an algorithm.
			eval("\$vote[contested] = $vote[tally];");
			$vote['contested'] = round((abs($vote['contested']) / $vote['total']), 2);
			
			# Reverse this number on the 0-1 scale.
			if ($vote['contested'] < .5)
			{
				$vote['contested'] = 1 - $vote['contested'];
			}
			elseif ($vote['contested'] > .5)
			{
				$vote['contested'] = 0 + (1 - $vote['contested']);
			}
		}
		
		# Update the votes table with this contested rating.
		$sql = 'UPDATE votes
				SET contested='.$vote['contested'];
		# If the vote wasn't contested, then it also wasn't partisan.
		if ($vote['contested'] == 0)
		{
			$sql .= ', partisanship=0';
		}
		$sql .= ' WHERE id='.$vote['id'];
		mysql_query($sql);
	}
}
