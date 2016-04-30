<?php

###
# GATHER AND RECORD CODE SECTIONS AFFECTED
# By Waldo Jaquith <waldo@jaquith.org>
# 01/15/2011?
#
# PURPOSE
# This trawls through the text of all bills from the current sesssion to identify sections of the
# Code of Virginia that they propose to amend, and records them.
#
# NOTES
# This won't work if called on its own--it will only function when invoked from within
# update_db.php.
###

// PROBLEMS
// * We've got to deal with "§§ 67-900 through 67-902 of the Code of Virginia are amended"
//	 -- only 67-900 and 67-902 are being picked up, not 67-901. This isn't *real* common, but
//   it's not unheard of. Somewhere between 1% and 2% of bills have such references.

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
@connect_to_db();

$sql = 'SELECT bills_full_text.id AS full_text_id, bills.id, bills_full_text.text
		FROM bills_full_text
		LEFT JOIN bills
			ON bills_full_text.bill_id=bills.id
		WHERE bills_full_text.text IS NOT NULL
		AND bills.session_id = ' . SESSION_ID . '
		AND
			(SELECT COUNT(*)
			FROM bills_section_numbers
			WHERE bill_id=bills_full_text.bill_id) = 0
		ORDER BY RAND()
		LIMIT 100';
$result = @mysql_query($sql);
if (@mysql_num_rows($result) > 0)
{

	while ($bill = @mysql_fetch_array($result))
	{
		$bill = array_map('stripslashes', $bill);
		# We want to strip out HTML (save for paragraphs), carriage returns, and extra spaces.
		# We're basically just looking for straight text here.
		$bill['text'] = strip_tags($bill['text'], '<p>');
		$bill['text'] = str_replace("\r", ' ', $bill['text']);
		$bill['text'] = str_replace("\n", ' ', $bill['text']);
		$bill['text'] = preg_replace('/\s+/', ' ', $bill['text']);
		
		# Just test to see if this is even a bill that affects the state code. If it doesn't,
		# then we can quit our work on this bill and move onto the next one.
		if ( (stristr($bill['text'], 'the Code of Virginia is amended') === false)
			&&
			(stristr($bill['text'], 'the Code of Virginia are amended') === false) )
		{
			continue;
		}
		
		# Now we know that this bill amends the code.
		# Let's explode the entire bill into paragraphs, so that we can isolate the paragraph
		# that contains the list of sections that this bill proposes to affect.
		$bill['text'] = explode('</p> <p>', $bill['text']);
		foreach ($bill['text'] as $paragraph)
		{
			if ( (stristr($paragraph, 'the Code of Virginia is amended') !== false)
				||
				(stristr($paragraph, 'the Code of Virginia are amended') !== false) )
			{
				$bill['paragraph'] = $paragraph;
				break;
			}
		}
		
		# We have now identified the paragraph that specifies the section(s) to be amended.
		# Now we've got to pull out every string that matches.
		preg_match_all('/([[0-9]{1,})([0-9A-Za-z\-\.]{0,3})-([0-9A-Za-z\-\.:]*)([0-9A-Za-z]{1,})/', $bill['paragraph'], $matches);
		
		# preg_match_all() stores matches in sub-arrays, which isn't useful to us, so we pull
		# the matches up a level.
		$matches = $matches[0];
		
		# We may have a trailing period on some matches, which makes trouble. Check every match
		# to see if it ends in a period, and, if so, hack it off.
		foreach ($matches as &$match)
		{
			if (substr($match, -1) == '.')
			{
				$match = substr($match, 0, -1);
			}
		}
		
		# Sometimes the same section is mentioned twice in a single paragraph, but we obviously
		# don't want two records of that.
		$matches = array_unique($matches);
		
		# We have to pass this array by reference to work around a bug in PHP that sometimes
		# causes the pointer to fail to advance.
		foreach ($matches as &$match)
		{

			# Insert all matched section numbers into the database. We don't bother to look up if
			# they actually exist, since many bills propose to create new laws, meaning that they
			# don't exist to be verified against.
			$sql = 'INSERT INTO bills_section_numbers
					SET full_text_id='.$bill['full_text_id'].', bill_id='.$bill['id'].',
					section_number="'.$match.'", date_created=now()';
			mysql_query($sql);
			
		}
	}
}

?>