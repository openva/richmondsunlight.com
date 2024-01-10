<?php

use Sunra\PhpSimple\HtmlDomParser;

class Import
{

	private $log;

	public function __construct(Log $log)
	{
        $this->log = $log;
    }

    /**
     * Retrieve a bill's text from the legislature's website.
     *
     * @return string
     */
    public function get_bill_text()
    {
        if (!isset($this->bill_number) || !isset($this->lis_session_id))
        {
            return FALSE;
        }

        # Retrieve the full text.
        $ch = curl_init('http://leg1.state.va.us/cgi-bin/legp504.exe?' . $this->lis_session_id . '+ful+'
            . mb_strtoupper($this->bill_number));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $text = curl_exec($ch);
        curl_close($ch);

        # Convert into an array.
        $text = explode("\n", $text);

        # Extract just the bill's text from the HTML.
        $text_clean = '';
        for ($i=0; $i<count($text); $i++)
        {
            if (!isset($start))
            {
                # These are the candidates strings of text that indicate that the bill is beginning
                # (as opposed to the HTML and general navigational text that precedes it).
                $preambles = array(
                    'HOUSE BILL NO. ',
                    'SENATE BILL NO. ',
                    'SENATE JOINT RESOLUTION NO. ',
                    'HOUSE JOINT RESOLUTION NO. ',
                    'SENATE RESOLUTION NO. ',
                    'HOUSE RESOLUTION NO. ');
                foreach ($preambles as $preamble)
                {
                    if (mb_stristr($text[$i], $preamble))
                    {
                        $start = TRUE;
                        break;
                    }
                }
            }

            # Finally, we're at the text of the bill.
            if (isset($start))
            {
                # This is the end of the text.
                if (mb_stristr($text[$i], '</body></html>'))
                {
                    break;
                }

                # Otherwise, add this line to our bill text.
                else
                {

                    # Determine where the header text ends and the actual law begins.
                    if (mb_stristr($text[$i], 'Be it enacted by'))
                    {
                        $law_start = TRUE;
                    }

                    if ($law_start == TRUE)
                    {
                        $text[$i] = str_replace('<i>', '<ins>', $text[$i]);
                        $text[$i] = str_replace('</i>', '</ins>', $text[$i]);
                    }

                    # Finally, append this line to our cleaned-up, stripped-down text.
                    $text_clean .= $text[$i] . ' ';
                }
            }
        }
        unset($text, $start, $law_start);

        # Strip out unacceptable tags.
        $text = trim(strip_tags($text_clean, '<p><b><i><em><strong><u><a><br><center><s><strike><ins>'));

        # In the unlikely possibility that we are now left with no text at all, then give up.
        if (empty($text))
        {
            return FALSE;
        }

        # Provide a domain name for all links.
        $text = str_ireplace('href="/', 'href="http://leg1.state.va.us/', $text);

        # Make the text available within the scope of the class.
        $this->text = $text;
    }

    /**
     * Take the legislature's HTML and make it less bad.
     *
     * @return string
     */
    public function clean_bill_text()
    {
        if (!isset($this->text))
        {
            return FALSE;
        }

        # Convert the legislature's Windows-1252 text to UTF-8.
        // Not necessary when using HTML Purifier.
        //$text = iconv('windows-1252', 'UTF-8', $text);

        # Fire up HTML Purifier.
        $purifier = new HTMLPurifier();

        # Run the text through HTML Purifier.
        $this->text = $purifier->purify($this->text);
    }

	/**
     * Turn the CSV array into well-formatted, well-named fields.
     *
     * @param array $bill
     * @return array
     */
	function prepare_bill($bill)
	{

		if (empty($bill))
		{
			return FALSE;
		}

		# Provide friendlier array element names.
		$bill['number'] = strtolower(trim($bill[0]));
		$bill['catch_line'] = trim($bill[1]);
		$bill['chief_patron_id'] = substr(trim($bill[2]), 1);
		$bill['chief_patron'] = trim($bill[3]);
		$bill['last_house_committee'] = trim($bill[4]);
		$bill['last_house_date'] = strtotime(trim($bill[6]));
		$bill['last_senate_committee'] = trim($bill[7]);
		$bill['last_senate_date'] = strtotime(trim($bill[9]));
		$bill['passed_house'] = trim($bill[15]);
		$bill['passed_senate'] = trim($bill[16]);
		$bill['passed'] = trim($bill[17]);
		$bill['failed'] = trim($bill[18]);
		$bill['continued'] = trim($bill[19]);
		$bill['approved'] = trim($bill[20]);
		$bill['vetoed'] = trim($bill[21]);

		# The following are versions of the bill's full text. Only the first pair need be
		# present. But the remainder are there to deal with the possibility that the bill is
		# amended X times.
		$bill['text'][0]['number'] = trim($bill[22]);
		$bill['text'][0]['date'] = date('Y-m-d', strtotime(trim($bill[23])));
		if (!empty($bill[24])) $bill['text'][1]['number'] = trim($bill[24]);
		if (!empty($bill[25])) $bill['text'][1]['date'] = date('Y-m-d', strtotime(trim($bill[25])));
		if (!empty($bill[26])) $bill['text'][2]['number'] = trim($bill[26]);
		if (!empty($bill[27])) $bill['text'][2]['date'] = date('Y-m-d', strtotime(trim($bill[27])));
		if (!empty($bill[28])) $bill['text'][3]['number'] = trim($bill[28]);
		if (!empty($bill[29])) $bill['text'][3]['date'] = date('Y-m-d', strtotime(trim($bill[29])));
		if (!empty($bill[30])) $bill['text'][4]['number'] = trim($bill[30]);
		if (!empty($bill[31])) $bill['text'][4]['date'] = date('Y-m-d', strtotime(trim($bill[31])));
		if (!empty($bill[32])) $bill['text'][5]['number'] = trim($bill[32]);
		if (!empty($bill[33])) $bill['text'][5]['date'] = date('Y-m-d', strtotime(trim($bill[33])));

		# Determine if this was introduced in the House or the Senate.
		if ($bill['number']{0} == 'h')
		{
			$bill['chamber'] = 'house';
		}
		elseif ($bill['number']{0} == 's')
		{
			$bill['chamber'] = 'senate';
		}

		# Set the last committee to be the committee in the chamber in which there was most recently
		# activity.
		if (empty($bill['last_house_date']))
		{
			$bill['last_house_date'] = 0;
		}
		if (empty($bill['last_senate_date']))
		{
			$bill['last_senate_date'] = 0;
		}
		if ($bill['last_house_date'] > $bill['last_senate_date'])
		{
			$bill['last_committee'] = substr($bill['last_house_committee'], 1);
			$bill['last_committee_chamber'] = 'house';
		}
		else
		{
			$bill['last_committee'] = substr($bill['last_senate_committee'], 1);
			$bill['last_committee_chamber'] = 'senate';
		}

		# Determine the latest status.
		if ($bill['approved'] == 'Y')
		{
			$bill['status'] = 'approved';
		}
		elseif ($bill['vetoed'] == 'Y')
		{
			$bill['status'] = 'vetoed';
		}
		# Only flag the bill as continued if it's from after Feb. '08.  This will
		# need to be updated periodically.
		elseif ($bill['continued'] == 'Y')
		{
			if (($bill['last_house_date'] > strtotime('01 February 2008'))
				&& ($bill['last_senate_date'] > strtotime('01 February 2008')))
			{
				$bill['status'] = 'continued';
			}
			else
			{
				$bill['status'] = 'failed';
			}
		}
		elseif ($bill['failed'] == 'Y') $bill['status'] = 'failed';
		elseif ($bill['passed'] == 'Y') $bill['status'] = 'passed';
		elseif ($bill['passed_senate'] == 'Y') $bill['status'] = 'passed senate';
		elseif ($bill['passed_house'] == 'Y') $bill['status'] = 'passed house';
		elseif (!empty($bill['last_senate_committee']) || !empty($bill['last_house_committee']))
		{
			$bill['status'] = 'in committee';
		}
		else
		{
			$bill['status'] = 'introduced';
		}

		# Create an instance of HTML Purifier to clean up the text.
		//$config = HTMLPurifier_Config::createDefault();
		//$purifier = new HTMLPurifier($config);

		# Purify the HTML and trim off the surrounding whitespace.
		//$bill['catch_line'] = trim($purifier->purify($bill['catch_line']));

		return $bill;

	}

    /**
     * Generate a list of all committees
     *
     * @return array
     */
	function create_committee_list()
	{

        $database = new Database;
        $db = $database->connect_mysqli();

		$log = new Log;

		$sql = 'SELECT id, lis_id, chamber
				FROM committees
				WHERE parent_id IS NULL
				ORDER BY id ASC';
		$result = mysqli_query($db, $sql);
		if ( $result === FALSE || mysqli_num_rows($result) == 0 )
		{
			$log->put('No committees were found in the database, which seems bad.', 8);
			return FALSE;
		}
		$committees = array();
		while ($committee = mysqli_fetch_assoc($result))
		{
			$committees[] = $committee;
		}

		return $committees;

	}
    
    /**
     * Generate a list of all legislators
     *
     * @return array
     */
	function create_legislator_list()
	{

        $database = new Database;
        $db = $database->connect_mysqli();

		$log = new Log;

		$sql = 'SELECT id, lis_id, chamber
				FROM representatives
				ORDER BY id ASC';
		$result = mysqli_query($db, $sql);
		if ( $result === FALSE || mysqli_num_rows($result) == 0 )
		{
			$log->put('No legislators were found in the database, which seems bad.', 8);
			return FALSE;
		}
		$legislators = array();
		while ($legislator = mysqli_fetch_assoc($result))
		{
			$legislators[] = $legislator;
		}

		return $legislators;

	}

	/**
     * Look up a legislator's ID.
     *
     * @param object $legislators
     * @param str $lis_id
     * @return str
     */
	function lookup_legislator_id($legislators, $lis_id)
	{

		# Determine the chamber.
		if ($lis_id{0} == 'H')
		{
			$chamber = 'house';
		}
		elseif ($lis_id{0} == 'S')
		{
			$chamber = 'senate';
		}

		# Bizarrely, LIS often (but not always) identifies the House speaker
		# as "Mr. Speaker" and uses the ID of "H0000," regardless of the real
		# ID of that delegate.  Translate that ID here.
		if ($lis_id == 'H0000')
		{
			$lis_id = HOUSE_SPEAKER_LIS_ID;
		}

		# Translate the LIS ID, stripping letters and removing leading 0s.
		$lis_id = preg_replace('/[A-Z]/D', '', $lis_id);
		$lis_id = round($lis_id);

		for ($i=0; $i<count($legislators); $i++)
		{
			if (($legislators[$i]['lis_id'] == $lis_id) && ($legislators[$i]['chamber'] == $chamber))
			{
				return $legislators[$i]['id'];
			}
		}
		return FALSE;

	}

	/**
     * Look up a committee's ID.
     *
     * @param array $committees
     * @param string $lis_id
     * @return string
     */
	function lookup_committee_id($committees, $lis_id)
	{

		# Determine the chamber.
		if ($lis_id{0} == 'H')
		{
			$chamber = 'house';
		}
		elseif ($lis_id{0} == 'S')
		{
			$chamber = 'senate';
		}

		# Translate the LIS ID, stripping letters and removing leading 0s.
		$lis_id = substr($lis_id, 1, 2);
		$lis_id = round($lis_id);

		foreach ($committees as $committee)
		{
			if (($committee['lis_id'] == $lis_id) && ($committee['chamber'] == $chamber))
			{
				return $committee['id'];
			}
		}
		
		return FALSE;

	}
	
	/**
     * Turn committee member CSV into an array ready to be inserted into the database
     *
     * @param string $csv
     * @param array $committees
     * @param array $legislators
     * @return array
     */
	function committee_members_csv_parse($csv, $committees, $legislators)
	{

		if ( empty($csv) || !is_array($committees) || !is_array($legislators) )
		{
			return FALSE;
		}

		/*
		 * Turn this CSV into a proper, indexed array
		 */
		$csv = explode("\n", $csv);
		$labels = str_getcsv($csv[0]);
		unset($csv[0]);
		foreach($csv as $row)
		{
			$members[] = array_combine($labels, str_getcsv($row));
		}
		
		if (!$members)
		{
			return FALSE;
		}

		foreach ($members as &$member)
		{
			$member['committee_id'] = Import::lookup_committee_id($committees, $member['CMB_COMNO']);
			$member['legislator_id'] = Import::lookup_legislator_id($legislators, $member['CMB_MBRNO']);
		}

		return $members;

	}

	/*
	 * fetch_photo()
	 *
	 * Retrieves a legislator photo from a provided URL and stores it.
	 */
	public function fetch_photo($url, $shortname)
	{

		if (empty($url) && empty($shortname))
		{
			return false;
		}

		/*
		 * Retrieve the photo from the remote server
		 */
		$photo = file_get_contents($url);
		
		if ($photo == false)
		{
			return false;
		}

		/*
		 * Store the file without an extension (we don't know the image format)
		 */
		$filename = $shortname;
		if (file_put_contents($filename, $photo) == false)
		{
			return false;
		}

		/*
		 * Try to identify the file format
		 */
		$filetype = mime_content_type($filename);
		if (stristr($filetype, 'image/jpeg'))
		{
			rename($filename, $filename.'.jpg');
			$filename = $filename.'.jpg';
		}
		elseif (stristr($filetype, 'image/png'))
		{
			rename($filename, $filename.'.png');
			$filename = $filename.'.png';
		}

		return $filename;
		
	}

	/*
	 * deactivate_legislator()
	 * 
	 * Sets a legislator as having left office.
	 */
	public function deactivate_legislator($id)
	{

		if (!isset($id))
		{
			return false;
		}

		/*
		 * LIS IDs are preceded with an H or an S, but we don't use those within the database,
		 * so strip those out.
		 */
		$id = preg_replace('/[H,S]/', '', $id);

		/*
		 * Determine what date to use to mark the legislator as no longer in office.
		 * 
		 * If it's November or December of an odd-numbered year, then the legislator's end date is
		 * the day before the next session starts.
		 */
		if (date('m') >= 11 && date('Y') % 2 == 1)
		{

			/*
			* See if we know when the next session starts.
			*/
			$sql = 'SELECT date_started
					FROM sessions
					WHERE date_started > now()';
			$stmt = $GLOBALS['db']->prepare($sql);
			$stmt->execute();
			$session = $stmt->fetch(PDO::FETCH_OBJ);
			if (count($session) > 0)
			{
				$date_ended = $session->date_started;
			}

			/*
			 * If we don't know when the next session starts, go with January 1.
			 */
			else
			{
				$date_ended = date('Y') + 1 . '-01-01';
			}

		}

		/*
		 * If this is not post-election, just make the date yesterday.
		 */
		else
		{
			$date_ended = date('Y-m-d', strtotime('-1 day'));
		}

		$sql = 'UPDATE representatives
				SET date_ended="' . $date_ended . '"
				WHERE lis_id="'. $id .'"';
		$stmt = $GLOBALS['dbh']->prepare($sql);
		$result = $stmt->execute();

		return $result;

	} // deactivate_legislator()

	/*
	 * add_legislator()
	 *
	 * Creates a new record for a legislator, requiring as input all data about the legislator to be
	 * added to the database. All array keys must have the same names as the database columns.
	 */
	public function add_legislator($legislator)
	{

		if (!isset($legislator) || !is_array($legislator))
		{
			return false;
		}

		/*
		* All of these values must be defined in order to create a record.
		*/
		$required_fields = array(
			'name_formal' => true,
			'name' => true,
			'name_formatted' => true,
			'shortname' => true,
			'chamber' => true,
			'district_id' => true,
			'date_started' => true,
			'party' => true,
			'lis_id' => true,
			'email' => true,
		);

		/*
		 * If any required values are missing, give up.
		 */
		$missing_fields = array_diff_key($required_fields, $legislator);
		if (count($missing_fields) > 0)
		{

			$this->log->put('Missing one or more required fields (' . implode(',', $missing_fields)
				. ') to add a record for ' . $legislator['name_formal'], 6);
			return false;

		}

		/*
		 * Make sure that there is not already a record for this shortname.
		 */
		$sql = 'SELECT *
				FROM representatives
				WHERE shortname="' . $legislator['shortname'] . '"';
		$stmt = $GLOBALS['dbh']->prepare($sql);
		$stmt->execute();
		$existing = $stmt->fetchAll(PDO::FETCH_OBJ);

		if (count($existing) > 0)
		{

			$error = 'Not creating a record for ' . $legislator['name_formatted'] .' because '
				. ' there is already a record for ' . $legislator['shortname'] . ' in the '
				. 'database. This legislator must be added manually. Use this info: ';
			foreach ($legislator as $key => $value)
			{
				$error .= $key . ': ' . $value . "\n";
			}
			$this->log->put($error, 6);

			return false;

		}

		/*
		 * LIS IDs are preceded with an "H" or an "S," but we don't use those within the
		 * database, so strip that out.
		 */
		$legislator['lis_id'] = preg_replace('/[H,S]/', '', $legislator['lis_id']);

		/*
		 * Build the SQL query
		 */
		$sql = 'INSERT INTO representatives SET ';
		foreach ($legislator as $key=>$value)
		{
			$sql .= $key.'="' . addslashes($value) . '", ';
		}

		$sql .= 'date_created=now()';

		/*
		 * Insert the legislator record
		 */
		$stmt = $GLOBALS['dbh']->prepare($sql);
		$result = $stmt->execute();
		if ($result == false)
		{
			$this->log->put('Error: Legislator record could not be added.' . "\n" . $sql . "\n", 6);
			return false;
		}
		
		return true;

	} // add_legislator()



	/*
	 * update_legislator()
	 *
	 * Updates an existing record for a legislator. All array keys must have the same names as the
	 * database columns.
	 */
	public function update_legislator($legislator)
	{

		if ( !isset($legislator) || !is_array($legislator) || empty($legislator['id']) )
		{
			return false;
		}

		/*
		* These are the only fields that may be updated automatically
		*/
		$allowed_fields = array(
			'email' => true,
			'address_richmond' => true,
			'address_district' => true,
			'phone_richmond' => true,
			'phone_district' => true,
			'race' => true,
			'sex' => true,
			'url' => true,
			'sbe_id' => true,
			'place' => true,
		);

		/*
		 * See if any of these fields are found within $legislator
		 */
		$changed_fields = array_intersect_key($allowed_fields, $legislator);
		if (count($changed_fields) == 0)
		{
			return;
		}

		/*
		 * LIS IDs are preceded with an "H" or an "S," but we don't use those within the
		 * database, so strip that out.
		 */
		$legislator['lis_id'] = preg_replace('/[H,S]/', '', $legislator['lis_id']);

		/*
		 * Build the SQL query
		 */
		$sql = 'UPDATE representatives SET ';
		foreach ($changed_fields as $key=>$value)
		{
			$sql .= $key.'="' . addslashes($legislator[$key]) . '", ';
		}

		$sql .= 'WHERE id=' . $legislator['id'];

		/*
		 * Update the legislator record
		 */
		$stmt = $GLOBALS['dbh']->prepare($sql);
		echo $sql;
		/*$result = $stmt->execute();
		if ($result == false)
		{
			$this->log->put('Error: Legislator record could not be updated.' . "\n" . $sql . "\n", 6);
			return false;
		}*/
		
		return true;

	} // update_legislator

	/*
	 * fetch_legislator_data()
	 * 
	 * Retrieves data about a legislator from the General Assembly's website, requiring as input the
	 * chamber name (house or senate) and the legislator's LIS ID.
	 */
	public function fetch_legislator_data($chamber, $lis_id)
	{

		if ( empty($chamber) || empty($lis_id) )
		{
			return false;
		}

		/*
		 * Fetch delegate information
		 */
		if ($chamber == 'house')
		{

			/*
			 * Fetch the HTML and save parse the DOM.
			 */
			$url = 'https://virginiageneralassembly.gov/house/members/members.php?ses=' . SESSION_YEAR
				. '&id=' . $lis_id;
			$html = file_get_contents($url);
			
			if ($html === false)
			{
				return false;
			}

			$dom = HtmlDomParser::str_get_html($html);
			if ($dom === false)
			{
				return false;
			}

			/*
			 * The array we'll store legislator data in.
			 */
			$legislator = array();

			$legislator['chamber'] = 'house';
			$legislator['lis_id'] = preg_replace('[HS]', '', $lis_id);

			/*
			 * Get delegate name.
			 */
			preg_match('/>Delegate (.+)</', $html, $matches);
			$legislator['name'] = trim($matches[1]);
			unset($matches);

			/*
			 * When delegates are elected, but not yet seated, LIS will call them "Delegate Elect."
			 * Remove "Elect" if it appears in the name.
			 */
			$legislator['name'] = str_replace('Elect ', '', $legislator['name']);
			
			/*
			 * Remove any nickname.
			 */
			$legislator['name'] = preg_replace('/ \(([A-Za-z]+)\) /', '', $legislator['name']);

			/*
			 * Sometimes we wind up with double spaces in legislators' names, so remove those.
			 */
			$legislator['name'] = trim(preg_replace('/\s{2,}/', ' ', $legislator['name']));

			/*
			 * Preserve this version of their name as their formal name
			 */
			$legislator['name_formal'] = trim($legislator['name']);

			/*
			 * Remove any suffix
			 */
			$suffixes = array('Jr.', 'Sr.', 'I', 'II', 'III', 'IV');
			foreach ($suffixes as $suffix)
			{
				if (substr( ($legislator['name']), strlen($suffix)*-1, strlen($suffix) ) == $suffix)
				{
					$legislator['name'] = trim(substr($legislator['name'], 0, strlen($suffix)*-1));
				}
			}

			/*
			 * Set aside the legislator's name in this format for use when creating the shortname
			 */
			$shortname = $legislator['name'];
			
			/*
			 * Remove any middle initials, but only if they're surrounded by spaces on either side.
			 * (Otherwise, e.g. "K.C. Smith" would become "Smith.")
			 */
			$legislator['name'] = preg_replace('/ [A-Z]\. /', ' ', $legislator['name']);

			/*
			 * Get delegate's preferred first name.
			 */
			preg_match('/>Preferred Name: ([a-zA-Z]+)</', $html, $matches);
			if (!empty($matches))
			{
				$legislator['nickname'] = trim($matches[1]);
				unset($matches);
			}

			/*
			 * Save the legislator's name in Lastname, Firstname format.
			 */
			if (isset($legislator['nickname']))
			{
				$legislator['name'] = substr($legislator['name'], strripos($legislator['name'], ' ')+1)
					. ', ' . $legislator['nickname'];
			}
			else
			{
				$last_space = strripos($legislator['name'], ' ');

				if ($last_space !== false)
				{
					$legislator['name'] =
						substr($legislator['name'], $last_space + 1) .
						', ' .
						substr($legislator['name'], 0, $last_space);
				}
			}
			$legislator['name'] = preg_replace('/\s{2,}/', ' ', $legislator['name']);

			/*
			 * We no longer need a nickname.
			 */
			if (isset($legislator['nickname']))
			{
				unset($legislator['nickname']);
			}

			/*
			 * Format delegate's shortname.
			 */
			preg_match_all('([A-Za-z-]+)', $shortname, $matches);
			$legislator['shortname'] = '';
			$i=0;
			while ($i+1 < count($matches[0]))
			{
				$legislator['shortname'] .= $matches[0][$i][0];
				$i++;
			}
			$tmp = explode(', ', $legislator['name']);
			$legislator['shortname'] .= $tmp[0];
			$legislator['shortname'] = strtolower($legislator['shortname']);

			/*
			 * Get email address.
			 */
			preg_match('/mailto:(.+)"/', $html, $matches);
			$legislator['email'] = trim($matches[1]);
			unset($matches);

			/*
			 * Get delegate's start date.
			 */
			preg_match('/Member Since: (.+)</', $html, $matches);
			$legislator['date_started'] = date('Y-m-d', strtotime(trim($matches[1])));
			unset($matches);

			/*
			 * Get delegate's district number.
			 */
			preg_match('/([0-9]{1,2})([a-z]{2}) District/', $html, $matches);
			$legislator['district_number'] = $matches[1];
			unset($matches);

			/*
			 * Get capitol office address.
			 */
			preg_match('/Room Number:<\/span> ([E,W]([0-9]{3}))/', $html, $matches);
			if (isset($matches[1]))
			{
				$legislator['address_richmond'] = $matches[1];
				unset($matches);
			}

			/*
			 * Get capitol phone number.
			 */
			preg_match('/Office:([\S\s]*)(\(804\) ([0-9]{3})-([0-9]{4}))/', $html, $matches);
			$legislator['phone_richmond'] = substr(str_replace(') ', '-', $matches[2]), 1);
			unset($matches);

			/*
			 * Get district address.
			 */
			$tmp = 'Address: ' . $dom->find('div[class=memBioOffice]', 1)->plaintext;
			$legislator['address_district'] = str_replace('Address: District Office ', '', preg_replace('/\s{2,}/', ' ', $tmp));
			if (stripos($legislator['address_district'], 'Office:') !== false)
			{
				$legislator['address_district'] = trim(substr($legislator['address_district'], 0, stripos($legislator['address_district'], 'Office:')));
			}
			$legislator['address_district'] = trim ($legislator['address_district']);
			if ($legislator['address_district'] == ',')
			{
				unset($legislator['address_district']);
			}

			/*
			 * Get district phone number.
			 */
			$tmp = 'Address: ' . $dom->find('div[class=memBioOffice]', 1)->plaintext;
			preg_match('/(\(804\) ([0-9]{3})-([0-9]{4}))/', $html, $matches);
			$legislator['phone_district'] = substr(str_replace(') ', '-', $matches[0]), 1);

			/*
			 * Get delegate's photo URL.
			 */
			preg_match('/https:\/\/memdata\.virginiageneralassembly\.gov\/images\/display_image\/H[0-9]{4}/', $html, $matches);
			$legislator['photo_url'] = $matches[0];
			unset($matches);

			/*
			 * Get gender.
			 */
			preg_match('/Gender:<\/span> ([A-Za-z]+)/', $html, $matches);
			$legislator['sex'] = strtolower($matches[1]);
			unset($matches);

			/*
			 * Get delegate'srace.
			 */
			preg_match('/Race\(s\):<\/span> (.+)</', $html, $matches);
			$legislator['race'] = trim(strtolower($matches[1]));
			$races = array(
				'african american' => 'black',
				'caucasian' => 'white',
				'Asian American' => 'asian',
				'Asian American, Indian' => 'asian',
				'Hispanic, Latino' => 'latino',
				'none given' => '',
			);
			foreach ($races as $find => $replace)
			{
				if ($legislator['race'] == $find)
				{
					$legislator['race'] = $replace;
					break;
				}
			}
			unset($matches);

			/*
			 * Get delegate's political party.
			 */
			preg_match('/distDescriptPlacement">([D,I,R]{1}) -/', $html, $matches);
			$legislator['party'] = trim($matches[1]);
			unset($matches);

			/*
			 * Get delegate's personal website.
			 */
			preg_match('/Delegate\'s Personal Website[\s\S]+(http(.+))"/U', $html, $matches);
			$legislator['website'] = trim($matches[1]);
			if ($legislator['website'] == 'https://whosmy.virginiageneralassembly.gov/')
			{
				unset($legislator['website']);
			}
			unset($matches);

			/*
			 * Turn district number into a district ID
			 */
			$district = new District;
			$d = $district->info('house', $legislator['district_number']);
			$legislator['district_id'] = $d['id'];

			/*
			 * Get delegate's place name
			 */
			preg_match('/<th scope="col">District Office(.+)<td>([A-Za-z ]+), (VA|Virginia)(\s+)([0-9]{5})/sU', $html, $matches);
			if (isset($matches[2]))
			{
				$legislator['place'] = $matches[2];
			}

			/*
			 * Create formatted name
			 */
			$legislator['name_formatted'] = 'Del. ' . pivot($legislator['name']) . ' (' .
				$legislator['party'] . '-';
			// We don't always have the place name, due to incomplete LIS data
			if (!empty($legislator['place']))
			{
				$legislator['name_formatted'] .= $legislator['place'];
			}
			else
			{
				$legislator['name_formatted'] .= $legislator['district_number'];
			}
			$legislator['name_formatted'] .= ')';


			/*
			 * We no longer need the district number.
			 */
			unset($legislator['district_number']);

		} // fetch delegate

		/*
		 * Fetch senator data
		 */
		elseif ($chamber == 'senate')
		{

			/*
			 * Fetch the HTML and save parse the DOM. We use this page, as opposed to either of
			 * the other two legislator records on the GA's site, because it is available before
			 * either of the other two. It's nowhere near as detailed as apps.senate.virginia.gov,
			 * but it exists so it's got that going for it.
			 */
			$url = 'https://lis.virginia.gov/cgi-bin/legp604.exe?' . SESSION_LIS_ID . '+mbr+'
				. $lis_id;
			$html = file_get_contents($url);

			if ($html === false)
			{
				return false;
			}

			$dom = HtmlDomParser::str_get_html($html);
			if ($dom === false)
			{
				return false;
			}

			$legislator = [];

			$legislator['lis_id'] = $lis_id;
			$legislator['chamber'] = 'senate';

			/*
			 * Get the senator's name.
			 */
			$tmp = $dom->find('h3.subttl');
			preg_match('/Senator(.+)&/', $tmp[0], $matches);
			$legislator['name'] = trim($matches[1]);

			/*
			 * Get senator's preferred first name.
			 */
			preg_match('/ "(.+)" /', $legislator['name'], $matches);
			if (!empty($matches))
			{
				$legislator['nickname'] = trim($matches[1]);
				unset($matches);
			}

			/*
			 * Set aside the name to use later, when establishing the shortname.
			 */
			$shortname = pivot($legislator['name']);

			/*
			 * Generate a placeholder for the senator's formal name.
			 */
			$legislator['name_formal'] = pivot($legislator['name']);
			
			/*
			 * Remove any middle initials, but only if they're surrounded by spaces on either side.
			 * (Otherwise, e.g. "K.C. Smith" would become "Smith.")
			 */
			$legislator['name'] = preg_replace('/ [A-Z]\. /', ' ', $legislator['name']);

			/*
			 * Save the senator's name in Lastname, Firstname format.
			 */
			if (isset($legislator['nickname']))
			{
				$legislator['name'] = substr($legislator['name'], strripos($legislator['name'], ' ')+1)
					. ', ' . $legislator['nickname'];
			}
			else
			{
				$last_space = strripos($legislator['name'], ' ');

				if ($last_space !== false)
				{
					$legislator['name'] =
						substr($legislator['name'], $last_space + 1) .
						', ' .
						substr($legislator['name'], 0, $last_space);
				}
			}
			$legislator['name'] = preg_replace('/\s{2,}/', ' ', $legislator['name']);

			/*
			 * We no longer need a nickname.
			 */
			if (isset($legislator['nickname']))
			{
				unset($legislator['nickname']);
			}

			/*
			 * Get district number.
			 */
			if (preg_match('/Senate District ([0-9]{1,2})/', $html, $matches) == 1)
			{
				$legislator['district_number'] = trim($matches[1]);
				unset($matches);
			}

			/*
			 * Get the legislator's party affiliation.
			 */
			$tmp = $dom->find('h3.subttl');
			if (preg_match('/\(([DIR]{1})\)/', $tmp[0], $matches) == 1)
			{
				$legislator['party'] = trim($matches[1]);
				unset($matches);
			}

			/*
			 * Put together the email address.
			 */
			$legislator['email'] = 'district' . $legislator['district_number']
				. '@senate.virginia.gov';

			/*
			 * Get district address.
			 */
			if (preg_match('/Mailing address:<\/h4><ul class="linkNon">\s*(<li>(.+)\n*)<\/ul>/sU',
				$html, $matches) == 1)
			{
				$legislator['address_district'] = trim(strip_tags($matches[2]));
				if (strlen($legislator['address_district']) < 20)
				{
					unset($legislator['address_district']);
				}
				unset($matches);
			}

			/*
			 * Get place name
			 */
			if (preg_match('/(.+)\n(.+), Virginia/s', $legislator['address_district'],
				$matches) == 1)
			{
				$legislator['place'] = trim($matches[2]);
			}

			/*
			 * Create formatted name
			 */
			$legislator['name_formatted'] = 'Sen. ' . pivot($legislator['name']) . ' (' .
				$legislator['party'] . '-';
			
			/*
			 * We don't always have the place name, due to incomplete LIS data
			 */
			if (!empty($legislator['place']))
			{
				$legislator['name_formatted'] .= $legislator['place'];
			}
			else
			{
				$legislator['name_formatted'] .= $legislator['district_number'];
			}
			$legislator['name_formatted'] .= ')';

			/*
			 * Now fetch data from apps.senate.virginia.gov, which has a lot more data (albeit
			 * unavailable until a legislator is actually sworn in).
			 */
			$url = 'https://apps.senate.virginia.gov/Senator/memberpage.php?id=' . $lis_id;
			$html = file_get_contents($url);

			if ($html === true)
			{

				$dom = HtmlDomParser::str_get_html($html);
				if ($dom === false)
				{
					return false;
				}

				/*
				 * Get legislator photo.
				 */
				if (preg_match('/(Senator\/images\/member_photos\/[a-zA-Z0-9-]+)/', $html,
					$matches) == 1)
				{
					$legislator['photo_url'] = 'https://apps.senate.virginia.gov/' . trim($matches[0]);
					unset($matches);
				}

				/*
				 * Get legislator biography.
				 */
				if (preg_match('/Biography(.+?)<div class="lrgblacktext">(.+?)<\/div>/s',
					$html, $matches) == 1)
				{
					$legislator['bio'] = trim($matches[2]);
					$legislator['bio'] = str_replace("\n", ' ', $legislator['bio']);
					$legislator['bio'] = preg_replace('/\s+/', ' ', $legislator['bio']);
					unset($matches);
				}

				/*
				 * Get Richmond office number.
				 */
				preg_match('/Room No: ([0-9]+)/', $html, $matches);
				$legislator['address_richmond'] = trim($matches[1]);
				unset($matches);

				/*
				 * Get Richmond phone number.
				 */
				preg_match('/Session Office<\/strong>(.+?)Phone: \(804\) ([0-9]{3})-([0-9]{4})/s', $html, $matches);
				$legislator['phone_richmond'] = '804-' . $matches[2] . '-' . $matches[3];
				unset($matches);

				/*
				 * Get District phone number.
				 */
				preg_match('/District Office<\/strong>(.+?)Phone: \(([0-9]{3})\) ([0-9]{3})-([0-9]{4})/s', $html, $matches);
				if (count($matches) == 5)
				{
					$legislator['phone_district'] = $matches[2] . '-' . $matches[3] . '-' . $matches[4];
				}
				unset($matches);
			
			} // end fetching from apps.senate.virginia.gov

			/*
			 * Determine what date to use as the senator's start date. We have to do this because the
			 * senate provides no information anywhere whatsoever about when a senator started their
			 * term in office, bafflingly.
			 * 
			 * If it's November or December of an odd-numbered year, then the legislator's start date
			 * is the day the next session starts.
			 */
			if (date('m') >= 11 && date('Y') % 2 == 1)
			{

				/*
			  	 * See if we know when the next session starts.
				 */
				$sql = 'SELECT date_started
						FROM sessions
						WHERE date_started > now()';
				$stmt = $GLOBALS['db']->prepare($sql);
				$stmt->execute();
				$session = $stmt->fetch(PDO::FETCH_OBJ);
				if (count($session) > 0)
				{
					$legislator['date_started'] = $session->date_started;
				}

				/*
				 * If we don't know when the next session starts, go with January 1.
				 */
				else
				{
					$legislator['date_started'] = date('Y') + 1 . '-01-01';
				}
				
			}

			/*
			 * If this is not post-election, just make the date yesterday.
			 */
			else
			{
				$legislator['date_started'] = date('Y-m-d', strtotime('-1 day'));
			}

			/*
			 * Format senator's shortname.
			 */
			preg_match_all('([A-Za-z-]+)', $shortname, $matches);
			$legislator['shortname'] = '';
			$i=0;
			while ($i+1 < count($matches[0]))
			{
				$legislator['shortname'] .= $matches[0][$i][0];
				$i++;
			}
			$tmp = explode(', ', $legislator['name']);
			$legislator['shortname'] .= $tmp[0];
			$legislator['shortname'] = strtolower($legislator['shortname']);
			
			/*
			 * Turn district number into a district ID
			 */
			$district = new District;
			$d = $district->info('senate', $legislator['district_number']);
			$legislator['district_id'] = $d['id'];
			$district = null;

			/*
			 * We no longer need the district number.
			 */
			unset($legislator['district_number']);

			print_r($legislator);

		} // fetch senator

		/*
		 * Clean up or enhance data collected
		 *
		 * Instead of repeating identical data transformations for delegates and senators, perform
		 * common transformations here.
		 */

		 /*
		  * Get location coordinates
		  */
		$location = new Location();
		if (!empty($legislator['address_district']))
		{
			$location->address = $legislator['address_district'];
		}
		elseif (!empty($legislator['place']))
		{
			$location->address = $legislator['place'] . ', VA';
		}
		if ( !empty($legislator['place']) && $location->get_coordinates($legislator['place']) != false )
		{
			$legislator['latitude'] = round($location->latitude, 2);
			$legislator['longitude'] = round($location->longitude, 2);
		}

		/*
		 * Standardize racial descriptions
		 *
		 * This is a little weird. The House of Delegates rightly allows members to specify any
		 * racial descriptor for themselves. But our database only has a few crude racial labels,
		 * because we don't actually use them for anything on the site, and because the House
		 * long didn't provide racial identifiers (and the Senate still doesn't), requiring taking
		 * a guess when adding legislators. The correct thing to do here would be to modify
		 * the database to allow arbitrary descriptors to be entered. But I'm not prepared to do
		 * that at this moment, so instead I'm going to collapse provided race designators into
		 * a few overly simplistic categories. Again, this isn't actually being surfaced anywhere,
		 * so there's no impact.
		 */
		$race_map = array(
			'caucasian' => 'white',
			'hispanic' => 'latino',
			'african american' => 'black',
			'asian american' => 'asian',
			'middle eastern' => 'other'
		);
		if (!empty($legislator['race']))
		{
			if (array_key_exists($legislator['race'], $race_map))
			{
				$legislator['race'] = $race_map[$legislator{'race'}];
			}
			// If multiple races are listed, don't record anything
			elseif (stristr($legislator['race'], ','))
			{
				$legislator['race'] = '';
			}
		}

		/*
		 * Drop any array elements with blank contents.
		 */
		foreach ($legislator as $key => $value)
		{
			if (!empty($value))
			{
				$newLegislator[$key] = $value;
			}
		}
		
		return $legislator;

	}

}
