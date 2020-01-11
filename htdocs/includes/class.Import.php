<?php

class Import
{

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
     * Fetch the latest bill CSV
     *
     * @param string $url
     * @return string
     */
	function update_bills_csv($url)
	{

		if (empty($url))
		{
			return FALSE;
		}

		$log = new Log;

		$bills = get_content($url);

		if (!$bills || empty($bills))
		{
			$log->put('BILLS.CSV doesn’t exist on legis.state.va.us.', 8);
			echo 'No data found on DLAS’s FTP server.';
			return FALSE;
		}

		# If the MD5 value of the new file is the same as the saved file, then there's nothing to update.
		if (md5($bills) == md5_file('bills.csv'))
		{
			$log->put('Not updating bills, because bills.csv has not been modified since it was last downloaded.', 2);
			return FALSE;
		}

		return $bills;

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
     * Fetch the CSV listing committee members
     *
     * @param string $dlas_session_id
     * @return string
     */
	function committee_members_csv_fetch($dlas_session_id = SESSION_LIS_ID)
	{

		$url = 'ftp://' . LIS_FTP_USERNAME . ':' . LIS_FTP_PASSWORD . '@legis.state.va.us/fromdlas/csv'
			. $dlas_session_id . '/CommitteeMembers.csv';$bills = get_content($url);

		$log = new Log;

		$members = get_content($url);

		if (!$members || empty($members))
		{
			$log->put('CommitteeMembers.csv doesn’t exist on legis.state.va.us.', 8);
			echo 'No committee member data found on DLAS’s FTP server.';
			return FALSE;
		}

		$members = trim($members);

		# If the MD5 value of the new file is the same as the saved file, then there's nothing to update.
		if (md5($members) == md5_file('committee_members.csv'))
		{
			$log->put('Not updating committee members, because committee_members.csv has not been '
				. ' modified since it was last downloaded.', 2);
			return FALSE;
		}

		return $members;

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

}
