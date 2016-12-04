<?php

/*
 * WHY IS THIS NAMED "BILL2"?
 * Because naming it "Bill" breaks every single legislator-related page. Why? I have NO IDEA.
 */
class Bill2
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
		
		/*
		 * If this bill is from the present year, try to retrieve the bill ID from Memcached.
		 */
		if ($year == SESSION_YEAR)
		{
		
			$mc = new Memcached();
			$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
			$result = $mc->get('bill-' . $number);
			if ($mc->getResultCode() == 0)
			{
				return $result;
			}
			
		}
		
		/*
		 * Query the DB.
		 */
		$sql = 'SELECT bills.id
				FROM bills
				LEFT JOIN sessions
					ON bills.session_id=sessions.id
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
	
	function info()
	{
		
		# We'll accept the ID in either format.
		if (isset($this->id))
		{
			$id = $this->id;
		}
		
		# Don't proceed unless we have a bill ID.
		if (!isset($id))
		{
			return FALSE;
		}
		
		/*
		 * Connect to Memcached.
		 */
		$mc = new Memcached();
		$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		
		/*
		 * If this bill is cached in Memcached, retrieve it from there.
		 */
		$bill = $mc->get('bill-' . $id);
		if ($mc->getResultCode() === 1)
		{
			return unserialize($bill);
		}
		
		# RETRIEVE THE BILL INFO FROM THE DATABASE
		$sql = 'SELECT bills.id, bills.number, bills.session_id, bills.chamber,
				bills.catch_line, bills.chief_patron_id, bills.summary, bills.summary_hash,
				bills.full_text, bills.notes, bills.status, bills.impact_statement_id,
				bills.date_introduced, bills.outcome, bills2.number AS incorporated_into,
				bills.copatrons AS copatron_count, representatives.name AS patron,
				districts.number AS patron_district, sessions.year, sessions.lis_id AS session_lis_id,
				representatives.party AS patron_party, representatives.chamber AS patron_chamber,
				representatives.shortname AS patron_shortname, representatives.place AS patron_place,
				DATE_FORMAT(representatives.date_started, "%Y") AS patron_started,
				representatives.name_formatted as patron_name_formatted,
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
				LEFT JOIN bills AS bills2
					ON bills.incorporated_into=bills2.id
				WHERE bills.id=' . $id;
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			return false;
		}
		$bill = mysql_fetch_array($result, MYSQL_ASSOC);
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
		$bill['url'] = 'http://www.richmondsunlight.com/bill/'.$bill['year'].'/'
			.strtolower($bill['number']).'/';
		
		# If this bill has any copatrons, we want to gather up all of them and include them in the bill
		# array.
		if ($bill['copatron_count'] > 0)
		{
			$sql = 'SELECT representatives.shortname, representatives.name_formatted,
					representatives.partisanship
					FROM bills_copatrons
					LEFT JOIN representatives
						ON bills_copatrons.legislator_id=representatives.id
					WHERE bills_copatrons.bill_id='.$bill['id'].'
					ORDER BY representatives.chamber ASC, representatives.name ASC';
			$bill_result = @mysql_query($sql);
			while ($copatron = mysql_fetch_array($bill_result))
			{
				$copatron = array_map('stripslashes', $copatron);
				$bill['copatron'][] = $copatron;
			}
		}
		
		# Select all tags from the database.
		$sql = 'SELECT id, tag
				FROM tags
				WHERE bill_id=' . $bill['id'];
		$result = mysql_query($sql);
		
		# If there are any tags, display them.
		if (mysql_num_rows($result) > 0)
		{
			while ($tag = mysql_fetch_array($result))
			{
				$tag['tag'] = stripslashes($tag['tag']);
				# Save the tags.
				$bill['tags'][$tag{'id'}] = $tag['tag'];
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
				WHERE bills_status.bill_id = ' . $bill['id'] . '
				ORDER BY date_raw DESC, bills_status.id DESC';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			# Initialize this array.
			$bill['status_history'] = array();
			# Iterate through the status history.
			while ($status = mysql_fetch_array($result, MYSQL_ASSOC))
			{
				# Clean it up.
				$status = array_map('stripslashes', $status);
				
				# Append this status data to the status history array.
				$bill['status_history'][] = $status;
			}
		}
		
		# Place names mentioned.
		$sql = 'SELECT placename AS name, latitude, longitude
				FROM bills_places
				WHERE bill_id='.$bill['id'].'';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			$bill['places'] = array();
			while ($place = mysql_fetch_array($result))
			{
				$bill['places'][] = array_map('stripslashes', $place);
			}
		}
		
		# Duplicates of this bill.
		# Select all bills that share this summary.
		$sql = 'SELECT bills.number, bills.chamber, bills.catch_line, bills.status,
				representatives.name AS patron, sessions.year, bills.date_introduced
				FROM bills
				LEFT JOIN representatives
					ON bills.chief_patron_id = representatives.id
				LEFT JOIN sessions
					ON bills.session_id = sessions.id
				WHERE bills.session_id = ' . $bill['session_id'] . '
				AND bills.summary_hash = "' . $bill['summary_hash'] . '" AND bills.id != ' . $bill['id'] . '
				ORDER BY bills.date_introduced ASC, bills.chamber DESC';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			
			$bill['duplicates'] = array();
			
			# Build up an array of duplicates.
			while ($duplicate = mysql_fetch_array($result))
			{
				$duplicate = array_map('stripslashes', $duplicate);
				$bill['duplicates'][] = $duplicate;
			}
		}
		
		if (isset($bill['tags']))
		{
			# Display a list of related bills, by finding the bills that share the most tags with this
			# one.
			$sql = 'SELECT DISTINCT bills.id, bills.number, bills.catch_line,
					DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
					committees.name, sessions.year,
			
						(SELECT translation
						FROM bills_status
						WHERE bill_id=bills.id AND translation IS NOT NULL
						ORDER BY date DESC, id DESC
						LIMIT 1) AS status,
				
						(SELECT COUNT(*)
						FROM bills AS bills2
						LEFT JOIN tags AS tags2
							ON bills2.id=tags2.bill_id
						WHERE (';
			# Using an array of tags established above, when listing the bill's tags, iterate
			# through them to create the SQL. The actual tag SQL is built up and then reused,
			# though slightly differently, later on in the SQL query, hence the str_replace.
			$tags_sql = '';
			
			$i=0;
			foreach ($bill['tags'] as $tag)
			{
				$tags_sql .= 'tags2.tag = "' . $tag . '"';
				if ($i < (count($bill['tags']) - 1))
				{
					$tags_sql .= ' OR ';
				}
				$i++;
			}
			$sql .= $tags_sql;
			$tags_sql = str_replace('tags2', 'tags', $tags_sql);
			$sql .= ')
						AND bills2.id = bills.id
						) AS count
					FROM bills
					LEFT JOIN tags
						ON bills.id=tags.bill_id
					LEFT JOIN sessions
						ON bills.session_id=sessions.id
					LEFT JOIN committees
						ON bills.last_committee_id = committees.id
					WHERE (' . $tags_sql . ') AND bills.id != ' . $bill['id'] . '
					AND bills.session_id = ' . $bill['session_id'] . '
					AND bills.summary_hash != "' . $bill['summary_hash'] . '"
					ORDER BY count DESC
					LIMIT 5';
			
			$result = mysql_query($sql);
	
			if (mysql_num_rows($result) > 0)
			{
				$bill['related'] = array();
				while ($related = mysql_fetch_array($result, MYSQL_ASSOC))
				{
					$bill['related'][] = $related;
				}
			}
		}

		/*
		 * Cache this bill in Memcached, for one week.
		 */
		$mc->set('bill-' . $id, serialize($bill), (60 * 60 * 24 * 7));
		
		/*
		 * And cache the bill's number in Memcached, indefinitely, if the bill is from this
		 * year.
		 */
		if ($bill['year'] == SESSION_YEAR)
		{
			$mc->set('bill-' . $bill['number'], $bill['id']);
		}
		
		return $bill;
		
	} // function "info"
	
	/**
	 * Returns a list of PCREs for all defined terms that exist within the Code of Virginia for the
	 * affected section of the Code.
	 */
	function get_terms()
	{
		
		/*
		 * We must have a bill ID.
		 */
		if (!isset($this->bill_id))
		{
			return FALSE;
		}
		
		/*
		 * Get an array of all sections of the Code of Virginia mentioned in this bill.
		 */
		$code_sections = bill_sections($this->bill_id);
		
		if ($code_sections !== FALSE)
		{
		
			/*
			 * We need to include the section number in Javascript, since our API request (on
			 * hover over each term) relies on it.
			 */
			$this->javascript = '<script>var section_number = "' . $code_sections[0]['section_number'] . '";</script>';
		
			/*
			 * Connect to Memcached.
			 */
			$mc = new Memcached();
			$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		
			/*
			 * See if these terms are cached in Memcached.
			 */
			$this->term_pcres = $mc->get('definitions-' . $this->bill_id);
			if ($mc->getResultCode() === 0)
			{
				return TRUE;
			}
		
			/*
			 * The terms aren't cached in Memcached, so get them from the Virginia Decoded API.
			 */
				
			$url = 'https://vacode.org/api/dictionary/?key=zxo8k592ztiwbgre&section=';
			/*
			 * Just use the first cited section of the code. It ain't fancy, but it kind of
			 * works.
			 */
			$url .= $code_sections[0]['section_number'];
			$terms = get_content(urldecode($url));
			$terms = (array) json_decode($terms);
			if (count($terms) == 0)
			{
				return FALSE;
			}

			/*
			 * If we now have terms, put them to work.
			 */
			if ($terms !== FALSE)
			{

				/*
				 * Arrange our terms from longest to shortest. This is to ensure that the most specific
				 * terms are defined (e.g. "person of interest") rather than the broadest terms (e.g.
				 * "person").
				 */
				usort($terms, 'sort_by_length');

				/*
				 * Store a list of the dictionary terms as an array, which is required for
				 * preg_replace_callback, the function that we use to insert the definitions.
				 */
				$term_pcres = array();
				foreach ($terms as $term)
				{

					/*
					 * Step through each character in this word.
					 */
					for ($i=0; $i<strlen($term); $i++)
					{
						/*
						 * If there are any uppercase characters, then make this PCRE string case
						 * sensitive.
						 */
						if ( (ord($term{$i}) >= 65) && (ord($term{$i}) <= 90) )
						{
							$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/';
							$caps = TRUE;
							break;
						}
					}

					/*
					 * If we have determined that this term does not contain capitalized letters, then
					 * create a case-insensitive PCRE string.
					 */
					if (!isset($caps))
					{
						$term_pcres[] = '/\b'.$term.'(s?)\b(?![^<]*>)/i';
					}

					/*
					 * Unset our flag -- we don't want to have it set the next time through.
					 */
					if (isset($caps))
					{
						unset($caps);
					}
				}
			}
			
			/*
			 * Make the PCREs available externally.
			 */
			$this->term_pcres = $term_pcres;
			
			/*
			 * Save this list of definitions.
			 */
			$mc->set( 'definitions-' . $this->bill_id, $this->term_pcres );
		
			return TRUE;
			
		}
		
		return FALSE;
		
	} // end method get_Terms
	
	
	/**
	 * Generate a list of all textual changes for a bill
	 *
	 * @todo: Handle bills that amend multiple sections.
	 */
	function list_changes()
	{
		
		/*
		 * We must have bill text.
		 */
		if (!isset($this->text))
		{
			return FALSE;
		}
		
		/*
		 * Eliminate all HTML other than insertions and deletions.
		 */
		$this->text = strip_tags($this->text, '<s><ins>');
		
		/*
		 * Calculate a hash to use for caching.
		 */
		$this->text_hash = md5($this->text);
		
		/*
		 * See if we have this cached.
		 */
		$mc = new Memcached();
		$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		$this->changes = $mc->get( 'bill-changes-' . $this->text_hash );
		if ($mc->getResultCode() == 0)
		{
			return $this->changes;
		}
		unset($this->changes);
		
		/*
		 * If the phrase "A BILL to amend and reenact" is found in the first 500 characters of this
		 * bill, then it's amending an existing law.
		 */
		if (strpos( substr($this->text, 0, 500 ), 'A BILL to amend and reenact') === FALSE)
		{
			return FALSE;
		}
		
		/*
		 * Figure out where the law actually starts.
		 */
		$parts = preg_split('/(:{1})(\s+)(§{1})(\s{1})/', $this->text);
		if ( ($parts === FALSE) || count($parts) < 2 )
		{
			return FALSE;
		}
		$start = strlen($parts[0]);
		
		/*
		 * Hack off everything prior to the start of the proposed modifications.
		 */
		$this->text = substr($this->text, ($start + 10) );
		$start = strpos($this->text, "\n");
		$this->text = substr($this->text, ($start + 1) );

		/*
		 * Rewrap the lines.
		 */
		$this->text = str_replace("\n", ' ', $this->text);
		$this->text = str_replace('</p><p>', "</p>\n\n<p>", $this->text);
		
		/*
		 * Figure out what the text of the law is currently.
		 */
		$before = preg_replace('/<ins>(.+)<\/ins>/sU', '\\2', $this->text);
		$before = str_replace('<s>', '', $before);
		$before = str_replace('</s>', '', $before);
		
		/*
		 * Figure out what the text of the law would be under this bill.
		 */
		$after = preg_replace('/<s>(.+)<\/s>/sU', '\\2', $this->text);
		$after = str_replace('<ins>', '', $after);
		$after = str_replace('</ins>', '', $after);

		/*
		 * Extract the added and deleted text from this bill. Make an ungreedy match that includes
		 * newlines.
		 */
		preg_match_all('/(.{0,50})<(ins|s)>(.+)<\/(ins|s)>(.{0,50}?)/sU', $this->text, $matches);

		/*
		 * Establish an array in which we'll store the changes to the text.
		 */
		$this->changes = array();
		
		/*
		 * Iterate through every insertion and deletion.
		 */
		$i=0;
		foreach ($matches[2] as $key => $type)
		{
	
			/*
			 * Verbosely specify what type of change this is.
			 */
			if ($type == 'ins')
			{
				$type = 'insert';
			}
			else
			{
				$type = 'delete';
			}
			$this->changes[$i]['type'] = $type;
	
			/*
			 * Include the text in question.
			 */
			$this->changes[$i]['text'] = $matches[3][$i];
	
			/*
			 * Include the text and immediately precedes and follows the text in question.
			 */
			$this->changes[$i]['preceded_by'] = $matches[1][$i];
			$this->changes[$i]['followed_by'] = $matches[5][$i];
	
			/*
			 * Include both the original and the new text, which is to say that we apply the
			 * transformation.
			 */
			if ($type == 'insert')
			{
				$this->changes[$i]['original'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['followed_by'];
				$this->changes[$i]['new'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['text'] . $this->changes[$i]['followed_by'];
			}
			elseif ($type == 'delete')
			{
				$this->changes[$i]['original'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['text'] . $this->changes[$i]['followed_by'];
				$this->changes[$i]['new'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['followed_by'];
			}
	
			$this->changes[$i]['diff'] = $matches[0][$i];
	
			/*
			 * Indicate at what point we are in the text, which is useful as only an approximate
			 * measure, to help provide guidance as to where this patch should be applied.
			 */
			$this->changes[$i]['position'] = strpos($this->text, $this->changes[$i]['diff']);
	
			$i++;
	
		}

		/*
		 * If we failed to identify any changes.
		 */
		if (count($this->changes) == 0)
		{
			$this->changes = FALSE;
		}
		
		/*
		 * Cache the results for three days.
		 */
		$mc->set( 'bill-changes-' . $this->text_hash, $this->changes, (60 * 60 * 24 * 3) );
		
		return $this->changes;
		
	}
	
} // end class "Bill2"
