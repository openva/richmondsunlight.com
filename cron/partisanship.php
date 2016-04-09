<?php

###
# CALCULATE PARTISANSHIP
# By Waldo Jaquith <waldo@jaquith.org>
# 06/07/2009
#
# PURPOSE
# This uses copatroning statistics in order to assign a partisanship ranking to every legislator.
# It's a 0-100 scale, with 0 representing liberal and 100 representative conservative.
#
# NOTES
# This won't work if called on its own--it will only function when invoked from within
# update_db.php.
###

$sql = 'SELECT id
		FROM representatives
		WHERE date_ended IS NULL
		OR date_ended >= now()';
$result = mysql_query($sql);
if (mysql_num_rows($result) == 0)
{
	die();
}

while ($legislator = mysql_fetch_array($result))
{
	
	# COPATRONING STATS
	# Calculate the percentage of the bills copatroned by this legislator that were introduced by
	# each party.
	$sql = 'SELECT representatives.party, COUNT(*) AS number
			FROM bills_copatrons
			LEFT JOIN bills
				ON bills_copatrons.bill_id=bills.id
			LEFT JOIN representatives
				ON bills.chief_patron_id=representatives.id
			WHERE bills_copatrons.legislator_id='.$legislator['id'].'
			GROUP BY representatives.party';
	$result2 = @mysql_query($sql);
	$tmp = array();
	while ($copatron = @mysql_fetch_array($result2))
	{
		$tmp[$copatron{'party'}] = $copatron['number'];
	}
	$total = array_sum($tmp);
	if ($total > 0)
	{
		arsort($tmp);
		
		# Populate an array that we use to determine overall partisanship. 0 = Democratic and 100 =
		# Republican. Because our number is based on the majority support, we need to rescale it.
		if (key($tmp)=='D')
		{
			$tmp = round((current($tmp)/$total)*100);
			if ($tmp > 50)
			{
				$tmp = 50 - ($tmp - 50);
			}
		}
		else
		{
			$tmp = round((current($tmp)/$total)*100);
		}
		$partisanship[] = $tmp;
	}
	
	# Calculate the percentages of the legislators' party memberships who have cosponsored any bill
	# introduced by this legislator.
	// Using this "IN" clause is just ridiculous. The query takes a good .2 seconds, which is way
	// too long. There's got to be a faster way to do this.
	$sql = 'SELECT representatives.party, COUNT(*) AS number
			FROM bills_copatrons
			LEFT JOIN representatives
				ON bills_copatrons.legislator_id = representatives.id
			WHERE bills_copatrons.bill_id
			IN
				(SELECT id
				FROM bills
				WHERE chief_patron_id = '.$legislator['id'].')
			GROUP BY representatives.party';
	$result2 = @mysql_query($sql);
	$tmp = array();
	while ($copatron = @mysql_fetch_array($result2))
	{
		$tmp[$copatron{'party'}] = $copatron['number'];
	}
	$total = array_sum($tmp);
	if ($total > 0)
	{
		arsort($tmp);
		
		# Populate an array that we use to determine overall partisanship. 0 = Democratic and 100 =
		# Republican. Because our number is based on the majority support, we need to rescale it.
		if (key($tmp)=='D')
		{
			$tmp = round((current($tmp)/$total)*100);
			if ($tmp > 50)
			{
				$tmp = 50 - ($tmp - 50);
			}
		}
		else
		{
			$tmp = round((current($tmp)/$total)*100);
		}
		$partisanship[] = $tmp;
	}
	
	# Calculate the percentages of the legislators' party memberships who are in the overall pool
	# of bills copatroned by this legislator. Meaning, look at every bill that this legislator has
	# copatroned, and look at every other copatron of those bills, and calculate the percentage of
	# those copatrons that are Democrats, Republicans, and independents.
	// Using this "IN" clause is just ridiculous. The query takes a good .1 seconds, which is way
	// too long. There's got to be a faster way to do this.
	$sql = 'SELECT representatives.party, COUNT(*) AS number
			FROM bills_copatrons
			LEFT JOIN representatives
				ON bills_copatrons.legislator_id=representatives.id
			WHERE
				bills_copatrons.bill_id IN
					(SELECT bill_id
					FROM bills_copatrons
					WHERE legislator_id='.$legislator['id'].')
			GROUP BY representatives.party';
	$result2 = @mysql_query($sql);
	$tmp = array();
	while ($copatron = @mysql_fetch_array($result2))
	{
		$tmp[$copatron{'party'}] = $copatron['number'];
	}
	$total = array_sum($tmp);
	if ($total > 0)
	{
		arsort($tmp);
		
		# Populate an array that we use to determine overall partisanship. 0 = Democratic and 100 =
		# Republican. Because our number is based on the majority support, we need to rescale it.
		if (key($tmp)=='D')
		{
			$tmp = round((current($tmp)/$total)*100);
			if ($tmp > 50)
			{
				$tmp = 50 - ($tmp - 50);
			}
		}
		else
		{
			$tmp = round((current($tmp)/$total)*100);
		}
		$partisanship[] = $tmp;
	}
	
	
	# Calculate how partisan that this legislator's record is, in light of his copatroning habits.
	if ( isset($partisanship) && is_array($partisanship) )
	{
		# We have to scale this number relative to the makeup of the chamber. For instance,
		# somebody with a 50% rating in a chamber divided 50/50 is actually 0% partisan, not 50%
		# partisan.
		$sql = 'SELECT party, COUNT(*) AS number
				FROM representatives
				WHERE date_ended IS NULL 
				OR date_ended >= NOW( ) 
				GROUP BY party';
		$result2 = @mysql_query($sql);
		if (@mysql_num_rows($result2) > 0)
		{
			$tmp = array();
			while ($chamber = @mysql_fetch_array($result2))
			{
				$tmp[$chamber{'party'}] = $chamber['number'];
			}
			$total = array_sum($tmp);
			arsort($tmp);
			$chamber_makeup = round(current($tmp) / $total * 100);
			$partisanship = round(array_sum($partisanship) / count($partisanship));
			
			$sql = 'UPDATE representatives
					SET partisanship='.$partisanship.'
					WHERE id='.$legislator['id'];
			mysql_query($sql);
			unset($partisanship);
		}
	} // end parsing $partisanship array
} // end legislators while loop

