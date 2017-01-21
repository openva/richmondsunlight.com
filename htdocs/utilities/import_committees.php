<?php

die('You can’t just up and run this. Review the code before you run it. Set the date.');

include_once('../includes/settings.inc.php');
include_once('../includes/functions.inc.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# The date of the committee assignments.
$start_date = '2017-01-11';

# Get a list of all committees.
$sql = 'SELECT c1.id, c1.lis_id, c1.parent_id, c1.chamber, c2.lis_id AS parent_lis_id
		FROM committees AS c1
		LEFT JOIN committees AS c2
			ON c1.parent_id = c2.id
		WHERE c1.lis_id IS NOT NULL
		ORDER BY chamber ASC, lis_id ASC';
$result = mysql_query($sql);

# Iterate through the committees.
while ($committee = mysql_fetch_object($result))
{

	# Assemble a URL for the LIS committee page.
	$url = 'http://lis.virginia.gov/cgi-bin/legp604.exe?';
	$url .= SESSION_LIS_ID;
	if (empty($committee->parent_id)) $url .= '+com+';
	else $url .= '+sub+';
	if ($committee->chamber == 'house') $url .= 'H';
	else $url .= 'S';
	if (empty($committee->parent_id)) $url .= $committee->lis_id;
	else
	{
		$url .= str_pad($committee->parent_lis_id, 2, '0', STR_PAD_LEFT)
			. str_pad($committee->lis_id, 3, '0', STR_PAD_LEFT);
	}

	# Extract from the committee page a list of its members.
	$html = get_content($url);

	preg_match_all('/\+mbr\+[S|H]([0-9]{2,3})/', $html, $members);
	if (count($members[1]) == 0)
	{
		continue;
	}
	$members = $members[1];

	# Terminate the membership of every existing member of this committee.
	$sql = 'UPDATE committee_members
			SET date_ended = "' . $start_date . '"
			WHERE id = ' . $committee->id . '
			AND date_ended IS NULL';
	mysql_query($sql);

	# Step through the members list for this committee.
	$i=0;
	foreach ($members as $member)
	{
		
		# The first name is the committee chair.
		if ($i === 0)
		{
			$position = 'chair';
		}
		
		# Get our internal ID for this representative.
		$sql = 'SELECT id
				FROM representatives
				WHERE lis_id = "' . $member . '" AND chamber = "' . $committee->chamber . '"
				AND date_ended IS NULL';
		$result2 = mysql_query($sql);
		if (mysql_num_rows($result2) == 0)
		{
			echo '<p style="color: red;">Failed: ' . $committee->id . ' ' . $member . ' ' . $sql . '</p>';
		}
		else
		{
		
			$legislator = mysql_fetch_object($result2);
			
			$sql = 'INSERT INTO committee_members
					SET committee_id = ' . $committee->id . ',
					representative_id = ' . $legislator->id . ',
					date_started = "' . $start_date . '", date_created=now()';
			if (!empty($position))
			{
				$sql .= ', position="'.$position.'"';
			}
			echo '<p>'.$sql.'</p>';
			$result3 = mysql_query($sql);
			if (!$result3)
			{
				echo '<p>Failed: '.$committee_id.' '.$name.'</p>';
			}
		}
		
		unset($place);
		unset($name);
		unset($position);
		$i++;
	}
}
