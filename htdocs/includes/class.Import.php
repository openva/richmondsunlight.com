<?php

use Sunra\PhpSimple\HtmlDomParser;

/**
 * Imports, normalizes, and enriches bill and legislator data from external sources.
 */
class Import
{
    private $log;
    private $pdo;
    private $preferredNameCache = [];

    /** @var string|null */
    public $bill_number;

    /** @var string|null */
    public $lis_session_id;

    /** @var string|null */
    public $text;

    /**
     * Initialise the importer with a logger dependency.
     *
     * @param Log $log Logger instance for recording warnings and errors.
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Retrieve a bill's text from the legislature's website.
     *
     * @return bool|null False when retrieval fails; null on success after populating $this->text.
     */
    public function get_bill_text()
    {
        if (!isset($this->bill_number) || !isset($this->lis_session_id)) {
            return false;
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
        for ($i = 0; $i < count($text); $i++) {
            if (!isset($start)) {
                # These are the candidates strings of text that indicate that the bill is beginning
                # (as opposed to the HTML and general navigational text that precedes it).
                $preambles = array(
                    'HOUSE BILL NO. ',
                    'SENATE BILL NO. ',
                    'SENATE JOINT RESOLUTION NO. ',
                    'HOUSE JOINT RESOLUTION NO. ',
                    'SENATE RESOLUTION NO. ',
                    'HOUSE RESOLUTION NO. ');
                foreach ($preambles as $preamble) {
                    if (mb_stristr($text[$i], $preamble)) {
                        $start = true;
                        break;
                    }
                }
            }

            # Finally, we're at the text of the bill.
            if (isset($start)) {
                # This is the end of the text.
                if (mb_stristr($text[$i], '</body></html>')) {
                    break;
                }

                # Otherwise, add this line to our bill text.
                else {
                    # Determine where the header text ends and the actual law begins.
                    if (mb_stristr($text[$i], 'Be it enacted by')) {
                        $law_start = true;
                    }

                    if ($law_start == true) {
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
        if (empty($text)) {
            return false;
        }

        # Provide a domain name for all links.
        $text = str_ireplace('href="/', 'href="https://leg1.state.va.us/', $text);

        # Make the text available within the scope of the class.
        $this->text = $text;
    }

    /**
     * Sanitize the fetched bill text with HTML Purifier.
     *
     * @return bool|null False when no text is set; null after purification.
     */
    public function clean_bill_text()
    {
        if (!isset($this->text)) {
            return false;
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
     * Turn the CSV array into well-formatted, well-named bill fields.
     *
     * @param array $bill Raw CSV row indexed numerically.
     *
     * @return array|false Normalized bill data or false when input is empty.
     */
    function prepare_bill($bill)
    {

        if (empty($bill)) {
            return false;
        }

        # Provide friendlier array element names.
        $bill['number'] = strtolower(trim($bill[0]));
        $bill['catch_line'] = trim($bill[1]);
        $bill['chief_patron_id'] = intval(substr(trim($bill[2]), 1));
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
        if (!empty($bill[24])) {
            $bill['text'][1]['number'] = trim($bill[24]);
        }
        if (!empty($bill[25])) {
            $bill['text'][1]['date'] = date('Y-m-d', strtotime(trim($bill[25])));
        }
        if (!empty($bill[26])) {
            $bill['text'][2]['number'] = trim($bill[26]);
        }
        if (!empty($bill[27])) {
            $bill['text'][2]['date'] = date('Y-m-d', strtotime(trim($bill[27])));
        }
        if (!empty($bill[28])) {
            $bill['text'][3]['number'] = trim($bill[28]);
        }
        if (!empty($bill[29])) {
            $bill['text'][3]['date'] = date('Y-m-d', strtotime(trim($bill[29])));
        }
        if (!empty($bill[30])) {
            $bill['text'][4]['number'] = trim($bill[30]);
        }
        if (!empty($bill[31])) {
            $bill['text'][4]['date'] = date('Y-m-d', strtotime(trim($bill[31])));
        }
        if (!empty($bill[32])) {
            $bill['text'][5]['number'] = trim($bill[32]);
        }
        if (!empty($bill[33])) {
            $bill['text'][5]['date'] = date('Y-m-d', strtotime(trim($bill[33])));
        }

        # Determine if this was introduced in the House or the Senate.
        if ($bill['number'][0] == 'h') {
            $bill['chamber'] = 'house';
        } elseif ($bill['number'][0] == 's') {
            $bill['chamber'] = 'senate';
        }

        # Set the last committee to be the committee in the chamber in which there was most recently
        # activity.
        if (empty($bill['last_house_date'])) {
            $bill['last_house_date'] = 0;
        }
        if (empty($bill['last_senate_date'])) {
            $bill['last_senate_date'] = 0;
        }
        if ($bill['last_house_date'] > $bill['last_senate_date']) {
            $bill['last_committee'] = substr($bill['last_house_committee'], 1);
            $bill['last_committee_chamber'] = 'house';
        } else {
            $bill['last_committee'] = substr($bill['last_senate_committee'], 1);
            $bill['last_committee_chamber'] = 'senate';
        }

        # Determine the latest status.
        if ($bill['approved'] == 'Y') {
            $bill['status'] = 'approved';
        } elseif ($bill['vetoed'] == 'Y') {
            $bill['status'] = 'vetoed';
        }
        # Only flag the bill as continued if it's from after Feb. '08.  This will
        # need to be updated periodically.
        elseif ($bill['continued'] == 'Y') {
            if (
                ($bill['last_house_date'] > strtotime('01 February 2008'))
                && ($bill['last_senate_date'] > strtotime('01 February 2008'))
            ) {
                $bill['status'] = 'continued';
            } else {
                $bill['status'] = 'failed';
            }
        } elseif ($bill['failed'] == 'Y') {
            $bill['status'] = 'failed';
        } elseif ($bill['passed'] == 'Y') {
            $bill['status'] = 'passed';
        } elseif ($bill['passed_senate'] == 'Y') {
            $bill['status'] = 'passed senate';
        } elseif ($bill['passed_house'] == 'Y') {
            $bill['status'] = 'passed house';
        } elseif (!empty($bill['last_senate_committee']) || !empty($bill['last_house_committee'])) {
            $bill['status'] = 'in committee';
        } else {
            $bill['status'] = 'introduced';
        }

        /*
         * Deal with sporadic character encoding problems with catch lines.
         */
        $bill['catch_line'] = iconv('windows-1252', 'UTF-8', $bill['catch_line']);

        /*
         * Run the catch line through HTML Purifier.
         */
        $purifier = new HTMLPurifier();
        $bill['catch_line'] = trim($purifier->purify($bill['catch_line']));

        return $bill;
    }

    /**
     * Generate a list of all committees.
     *
     * @return array|false Array of committee rows or false when none are found.
     */
    function create_committee_list()
    {

        $database = new Database();
        $db = $database->connect_mysqli();

        $log = new Log();

        $sql = 'SELECT id, lis_id, chamber
				FROM committees
				WHERE parent_id IS NULL
				ORDER BY id ASC';
        $result = mysqli_query($db, $sql);
        if ($result === false || mysqli_num_rows($result) == 0) {
            $log->put('No committees were found in the database, which seems bad.', 8);
            return false;
        }
        $committees = array();
        while ($committee = mysqli_fetch_assoc($result)) {
            $committees[] = $committee;
        }

        return $committees;
    }

    /**
     * Generate a list of all legislators.
     *
     * @return array|false Array of legislator rows or false when none exist.
     */
    function create_legislator_list()
    {

        $database = new Database();
        $db = $database->connect_mysqli();

        $log = new Log();

        $sql = 'SELECT id, lis_id, chamber
				FROM representatives
				ORDER BY id ASC';
        $result = mysqli_query($db, $sql);
        if ($result === false || mysqli_num_rows($result) == 0) {
            $log->put('No legislators were found in the database, which seems bad.', 8);
            return false;
        }
        $legislators = array();
        while ($legislator = mysqli_fetch_assoc($result)) {
            $legislators[] = $legislator;
        }

        return $legislators;
    }

    /**
     * Look up a legislator's internal ID.
     *
     * @param array  $legislators Array of legislator rows containing lis_id and chamber.
     * @param string $lis_id      LIS identifier.
     *
     * @return int|string|false Matching ID or false when not found.
     */
    function lookup_legislator_id($legislators, $lis_id)
    {

        # Determine the chamber.
        if ($lis_id[0] == 'H') {
            $chamber = 'house';
        } elseif ($lis_id[0] == 'S') {
            $chamber = 'senate';
        }

        # Bizarrely, LIS often (but not always) identifies the House speaker
        # as "Mr. Speaker" and uses the ID of "H0000," regardless of the real
        # ID of that delegate.  Translate that ID here.
        if ($lis_id == 'H0000') {
            $lis_id = HOUSE_SPEAKER_LIS_ID;
        }

        # Translate the LIS ID, stripping letters and removing leading 0s.
        $lis_id = preg_replace('/[A-Z]/D', '', $lis_id);
        $lis_id = round($lis_id);

        for ($i = 0; $i < count($legislators); $i++) {
            if (($legislators[$i]['lis_id'] == $lis_id) && ($legislators[$i]['chamber'] == $chamber)) {
                return $legislators[$i]['id'];
            }
        }
        return false;
    }

    /**
     * Look up a committee's internal ID.
     *
     * @param array  $committees Committee rows containing lis_id and chamber.
     * @param string $lis_id     Committee LIS identifier.
     *
     * @return int|string|false Matching ID or false when not found.
     */
    function lookup_committee_id($committees, $lis_id)
    {

        # Determine the chamber.
        if ($lis_id[0] == 'H') {
            $chamber = 'house';
        } elseif ($lis_id[0] == 'S') {
            $chamber = 'senate';
        }

        # Translate the LIS ID, stripping letters and removing leading 0s.
        $lis_id = substr($lis_id, 1, 2);
        $lis_id = round($lis_id);

        foreach ($committees as $committee) {
            if (($committee['lis_id'] == $lis_id) && ($committee['chamber'] == $chamber)) {
                return $committee['id'];
            }
        }

        return false;
    }

    /**
     * Turn committee member CSV into an array ready to be inserted into the database.
     *
     * @param string $csv          Raw CSV payload keyed by column headers.
     * @param array  $committees   Committee lookup data.
     * @param array  $legislators  Legislator lookup data.
     *
     * @return array|false Normalized committee membership rows or false on failure.
     */
    function committee_members_csv_parse($csv, $committees, $legislators)
    {

        if (empty($csv) || !is_array($committees) || !is_array($legislators)) {
            return false;
        }

        /*
         * Turn this CSV into a proper, indexed array
         */
        $csv = explode("\n", $csv);
        $labels = str_getcsv($csv[0]);
        unset($csv[0]);
        foreach ($csv as $row) {
            $members[] = array_combine($labels, str_getcsv($row));
        }

        if (!$members) {
            return false;
        }

        foreach ($members as &$member) {
            $member['committee_id'] = Import::lookup_committee_id($committees, $member['CMB_COMNO']);
            $member['legislator_id'] = Import::lookup_legislator_id($legislators, $member['CMB_MBRNO']);
        }

        return $members;
    }

    /**
     * Retrieve a legislator photo from a provided URL and store it locally.
     *
     * @param string $url       Remote image URL.
     * @param string $shortname Legislator shortname used for local storage.
     *
     * @return string|false Stored filename (with extension) or false on failure.
     */
    public function fetch_photo($url, $shortname)
    {

        if (empty($url) && empty($shortname)) {
            return false;
        }

        /*
         * Retrieve the photo from the remote server
         */
        $photo = file_get_contents($url);

        if ($photo == false) {
            return false;
        }

        /*
         * Store the file without an extension (we don't know the image format)
         */
        $filename = $shortname;
        if (file_put_contents($filename, $photo) == false) {
            return false;
        }

        /*
         * Try to identify the file format
         */
        $filetype = mime_content_type($filename);
        if (stristr($filetype, 'image/jpeg')) {
            rename($filename, $filename . '.jpg');
            $filename = $filename . '.jpg';
        } elseif (stristr($filetype, 'image/png')) {
            rename($filename, $filename . '.png');
            $filename = $filename . '.png';
        }

        return $filename;
    }

    /**
     * Mark a legislator as having left office.
     *
     * @param string $id Legislator LIS identifier.
     *
     * @return bool True on success, false on failure.
     */
    public function deactivate_legislator($id)
    {

        if (!isset($id)) {
            return false;
        }

        /*
         * LIS IDs are preceded with an H or an S, but we don't use those within the database,
         * so strip those out.
         */
        $id = preg_replace('/[H,S]/', '', $id);

        /*
         * LIS has started sometimes left-padding with 0s, confusingly. Strip those out.
         */
        $id = ltrim($id, '0');

        /*
         * Determine what date to use to mark the legislator as no longer in office.
         *
         * If it's November or December of an odd-numbered year, then the legislator's end date is
         * the day before the next session starts.
         */
        if (date('m') >= 11 && date('Y') % 2 == 1) {
            /*
            * See if we know when the next session starts.
            */
            $sql = 'SELECT date_started
					FROM sessions
					WHERE date_started > now()';
            $stmt = $GLOBALS['db']->prepare($sql);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_OBJ);
            if (count($session) > 0) {
                $date_ended = $session->date_started;
            }

            /*
             * If we don't know when the next session starts, go with January 1.
             */
            else {
                $date_ended = date('Y') + 1 . '-01-01';
            }
        }

        /*
         * If this is not post-election, just make the date yesterday.
         */
        else {
            $date_ended = date('Y-m-d', strtotime('-1 day'));
        }

        $sql = 'UPDATE representatives
				SET date_ended="' . $date_ended . '"
				WHERE lis_id="' . $id . '"';
        $stmt = $GLOBALS['dbh']->prepare($sql);
        $result = $stmt->execute();

        return $result;
    } // deactivate_legislator()



    /**
     * Verify that a legislator is still listed in the General Assembly roster via the LIS API.
     *
     * @param string $lis_id Legislator LIS identifier.
     *
     * @return bool True when the legislator remains active, false otherwise.
     *
     * @throws Exception When the identifier cannot be normalized or is invalid.
     */
    public function legislator_in_csv($lis_id)
    {

        $lis_id = strtoupper(trim((string)$lis_id));
        if (!preg_match('/^[HS][0-9]+$/', $lis_id)) {
            throw new Exception('LIS ID is invalid');
        }

        $chamber = ($lis_id[0] === 'S') ? 'senate' : 'house';
        $member_number = $this->normalize_member_number($chamber, $lis_id);
        if ($member_number === false) {
            throw new Exception('Unable to normalize LIS ID: ' . $lis_id);
        }

        $session_code = '20' . SESSION_LIS_ID;
        $member = $this->fetch_member_record($member_number, $session_code);
        if ($member === false) {
            return false;
        }

        if (isset($member['MemberStatus']) && strtolower($member['MemberStatus']) !== 'active') {
            return false;
        }

        if (!empty($member['ServiceEndDate'])) {
            $end_timestamp = strtotime($member['ServiceEndDate']);
            if ($end_timestamp !== false && $end_timestamp < time()) {
                return false;
            }
        }

        return true;
    } //

    /**
     * Create a new legislator record with the provided data.
     *
     * @param array $legislator Associative array of legislator fields.
     *
     * @return bool True on success, false when validation fails or insertion errors occur.
     */
    public function add_legislator($legislator)
    {

        if (!isset($legislator) || !is_array($legislator)) {
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
        if (count($missing_fields) > 0) {
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

        if (count($existing) > 0) {
            $error = 'Not creating a record for ' . $legislator['name_formatted'] . ' because '
                . ' there is already a record for ' . $legislator['shortname'] . ' in the '
                . 'database. This legislator must be added manually. Use this info: ';
            foreach ($legislator as $key => $value) {
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
        foreach ($legislator as $key => $value) {
            $sql .= $key . '="' . addslashes($value) . '", ';
        }

        $sql .= 'date_created=now()';

        /*
         * Insert the legislator record
         */
        $stmt = $GLOBALS['dbh']->prepare($sql);
        $result = $stmt->execute();
        if ($result == false) {
            $this->log->put('Error: Legislator record could not be added.' . "\n" . $sql . "\n", 6);
            return false;
        }

        return true;
    } // add_legislator()

    /**
     * Generate a shortname slug for a legislator.
     *
     * @param string $casual Casual name in "Lastname, Firstname" format.
     * @param string $full   Full formal name.
     *
     * @return string Generated lowercase shortname.
     */
    function create_legislator_shortname($casual, $full)
    {
        // Break up any initialisms (e.g., "J.D." becomes "J. D.")
        $full = preg_replace('/(([A-Z]{1}\.)([A-Z]{1}\.))/', '$2 $3', $full);

        // Remove any nicknames
        $full = preg_replace('/("[A-Za-z]+")/', '', $full);

        // Remove any character that isn't a letter, hyphen, or a space
        $full = preg_replace('/[^A-Za-z- ]+/', '', $full);

        // Break the name up into each component
        preg_match_all('([A-Za-z-.]+)', $full, $matches);
        $components = $matches[0];

        // Iterate through and remove suffixes
        $suffixes = ['II', 'III', 'IV', 'V', 'Jr', 'Sr', 'Jr.', 'Sr.', 'Junior', 'Senior'];
        $i = 1;
        while ($i < count($components)) {
            if ($i + 1 == count($components)) {
                if (in_array($components[$i], $suffixes)) {
                    unset($components[$i]);
                }
            }
            $i++;
        }

        // Iterate through through each component and build up the shortname, omitting the final
        // one, because that's the last name.
        $shortname = '';
        $i = 1;
        foreach ($components as $component) {
            if ($i < count($components)) {
                $shortname .= $component[0];
            }
            $i++;
        }

        // Append the last name to the end, after stripping out anything that isn't a letter or a
        // hyphen.
        $tmp = explode(', ', $casual);
        $tmp[0] = preg_replace('/[^A-Za-z-]+/', '', $tmp[0]);
        $shortname .= $tmp[0];
        $shortname = strtolower($shortname);

        return $shortname;
    }

    /**
     * Update an existing legislator record using the supplied data.
     *
     * @param array $legislator Associative array containing an `id` key and any permitted fields.
     *
     * @return bool|null True when an update occurs, false on failure, null when no changes were provided.
     */
    public function update_legislator($legislator)
    {

        if (!isset($legislator) || !is_array($legislator) || empty($legislator['id'])) {
            return false;
        }

        $log = new Log();

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
            'latitude' => true,
            'longitude' => true,
            'district_id' => true,
        );

        /*
         * See if any of these fields are found within $legislator
         */
        $changed_fields = array_intersect_key($allowed_fields, $legislator);
        if (count($changed_fields) == 0) {
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
        foreach ($changed_fields as $key => $value) {
            $sql .= $key . '="' . addslashes($legislator[$key]) . '", ';
        }

        $sql = substr($sql, 0, -2) . ' ';

        $sql .= 'WHERE id=' . $legislator['id'];

        /*
         * Update the legislator record
         */
        $stmt = $GLOBALS['dbh']->prepare($sql);
        $result = $stmt->execute();
        if ($result == false) {
            $this->log->put('Error: Legislator record could not be updated.' . "\n" . $sql . "\n", 6);
            return false;
        }

        $log->put('Refreshed the legislator record for ' . $legislator['name_formatted'] . '.', 2);

        return true;
    } // update_legislator

    /**
     * Retrieve legislator data by scraping the General Assembly website.
     *
     * @param string $chamber Chamber identifier (`house` or `senate`).
     * @param string $lis_id  Legislator LIS identifier.
     *
     * @return array|false Parsed legislator data or false on failure.
     */
    public function fetch_legislator_data($chamber, $lis_id)
    {

        if (empty($chamber) || empty($lis_id)) {
            return false;
        }

        /*
         * Fetch delegate information
         */
        if ($chamber == 'house') {
            /*
             * Fetch the HTML and save parse the DOM.
             */
            if (stripos($lis_id, 'H') === false) {
                $url_id = 'H' . str_pad($lis_id, 4, '0', STR_PAD_LEFT);
            } else {
                $url_id = $lis_id;
            }

            $url = 'https://virginiageneralassembly.gov/house/members/members.php?id=' . $url_id;
            $html = file_get_contents($url);

            if ($html === false) {
                return false;
            }

            $dom = HtmlDomParser::str_get_html($html);
            if ($dom === false) {
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
            foreach ($suffixes as $suffix) {
                if (substr(($legislator['name']), strlen($suffix) * -1, strlen($suffix)) == $suffix) {
                    $legislator['name'] = trim(substr($legislator['name'], 0, strlen($suffix) * -1));
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
            if (!empty($matches)) {
                $legislator['nickname'] = trim($matches[1]);
                unset($matches);
            }

            /*
             * Save the legislator's name in Lastname, Firstname format.
             */
            if (isset($legislator['nickname'])) {
                $legislator['name'] = substr($legislator['name'], strripos($legislator['name'], ' ') + 1)
                    . ', ' . $legislator['nickname'];
            } else {
                $last_space = strripos($legislator['name'], ' ');

                if ($last_space !== false) {
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
            if (isset($legislator['nickname'])) {
                unset($legislator['nickname']);
            }

            /*
             * Format delegate's shortname.
             */
            $legislator['shortname'] = $this->create_legislator_shortname(
                $shortname,
                $legislator['name']
            );

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
            preg_match('/([0-9]{1,3})([a-z]{2}) District/', $html, $matches);
            $legislator['district_number'] = $matches[1];
            unset($matches);

            /*
             * Get capitol office address.
             */
            preg_match('/Room Number:<\/span> ([E,W]?([0-9]{3}))/', $html, $matches);
            if (isset($matches[1])) {
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
            if (stripos($legislator['address_district'], 'Office:') !== false) {
                $legislator['address_district'] = trim(substr($legislator['address_district'], 0, stripos($legislator['address_district'], 'Office:')));
            }
            // Deal with multiple addresses in this segment, getting only the physical address
            if (stripos($legislator['address_district'], 'Mailing Address:') !== false) {
                $end = stripos($legislator['address_district'], 'Mailing Address:');
                $legislator['address_district'] = substr($legislator['address_district'], 0, $end);
                $legislator['address_district'] = str_replace('Physical Address:', '', $legislator['address_district']);
            }

            $legislator['address_district'] = trim($legislator['address_district']);
            if ($legislator['address_district'] == ',') {
                unset($legislator['address_district']);
            }

            /*
             * Get district phone number.
             */
            preg_match('/Office:<\/span> (\(([0-9]{3})\) ([0-9]{3})-([0-9]{4}))/', $html, $matches);
            $legislator['phone_district'] = substr(str_replace(') ', '-', $matches[1]), 1);

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
             * Get delegate's race.
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
            foreach ($races as $find => $replace) {
                if ($legislator['race'] == $find) {
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
            $legislator['url'] = trim($matches[1]);
            if (stripos($legislator['url'], '.gov') !== false) {
                unset($legislator['url']);
            }
            unset($matches);

            /*
             * Turn district number into a district ID
             */
            $district = new District();
            $d = $district->info('house', $legislator['district_number']);
            $legislator['district_id'] = $d['id'];

            /*
             * Get delegate's place name
             */
            preg_match('/<th scope="col">District Office(.+)<td>([A-Za-z ]+), (VA|Virginia)(\s+)([0-9]{5})/sU', $html, $matches);
            if (isset($matches[2])) {
                $legislator['place'] = $matches[2];
            }

            /*
             * Create formatted name
             */
            $legislator['name_formatted'] = 'Del. ' . pivot($legislator['name']) . ' (' .
                $legislator['party'] . '-';
            // We don't always have the place name, due to incomplete LIS data
            if (!empty($legislator['place'])) {
                $legislator['name_formatted'] .= $legislator['place'];
            } else {
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
        elseif ($chamber == 'senate') {
            /*
             * Fetch the HTML and save parse the DOM. We use this page, as opposed to either of
             * the other two legislator records on the GA's site, because it is available before
             * either of the other two. It's nowhere near as detailed as apps.senate.virginia.gov,
             * but it exists so it's got that going for it.
             */
            if ($lis_id[0] == 'S') {
                $lis_id = substr($lis_id, 1);
            }
            $url = 'https://legacylis.virginia.gov/cgi-bin/legp604.exe?' . SESSION_LIS_ID . '+mbr+S'
                . $lis_id;
            $html = file_get_contents($url);

            if ($html === false) {
                return false;
            }

            $dom = HtmlDomParser::str_get_html($html);
            if ($dom === false) {
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
            if (!empty($matches)) {
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
            if (isset($legislator['nickname'])) {
                $legislator['name'] = substr($legislator['name'], strripos($legislator['name'], ' ') + 1)
                    . ', ' . $legislator['nickname'];
            } else {
                $last_space = strripos($legislator['name'], ' ');

                if ($last_space !== false) {
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
            if (isset($legislator['nickname'])) {
                unset($legislator['nickname']);
            }

            /*
             * Get district number.
             */
            if (preg_match('/Senate District ([0-9]{1,2})/', $html, $matches) == 1) {
                $legislator['district_number'] = trim($matches[1]);
                unset($matches);
            }

            /*
             * Get the senator's party affiliation.
             */
            $tmp = $dom->find('h3.subttl');
            if (preg_match('/\(([DIR]{1})\)/', $tmp[0], $matches) == 1) {
                $legislator['party'] = trim($matches[1]);
                unset($matches);
            }

            /*
             * Get the senator's email address.
             */
            if (preg_match('/senator(.+)@senate.virginia.gov/U', $html, $matches) == 1) {
                $legislator['email'] = $matches[0];
                unset($matches);
            }

            /*
             * Get district address.
             */
            if (
                preg_match(
                    '/Mailing address:<\/h4><ul class="linkNon">\s*(<li>(.+)\n*)<\/ul>/sU',
                    $html,
                    $matches
                ) == 1
            ) {
                $tmp = preg_replace('/\([0-9]{3}\) [0-9]{3}-[0-9]{4}/', '', $matches[1]);
                $legislator['address_district'] = trim(strip_tags($tmp));
                if (strlen($legislator['address_district']) < 20) {
                    unset($legislator['address_district']);
                }
                unset($matches);
            }

            /*
             * Get place name
             */
            if (
                preg_match(
                    '/(.+)\n(.+), Virginia/s',
                    $legislator['address_district'],
                    $matches
                ) == 1
            ) {
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
            if (!empty($legislator['place'])) {
                $legislator['name_formatted'] .= $legislator['place'];
            } else {
                $legislator['name_formatted'] .= $legislator['district_number'];
            }
            $legislator['name_formatted'] .= ')';

            /*
             * Now fetch data from apps.senate.virginia.gov, which has a lot more data (albeit
             * unavailable until a legislator is actually sworn in).
             */
            $url = 'https://apps.senate.virginia.gov/Senator/memberpage.php?id=' . $lis_id;
            $html = file_get_contents($url);

            if ($html === true) {
                $dom = HtmlDomParser::str_get_html($html);
                if ($dom === false) {
                    return false;
                }

                /*
                 * Get legislator photo.
                 */
                if (
                    preg_match(
                        '/(Senator\/images\/member_photos\/[a-zA-Z0-9-]+)/',
                        $html,
                        $matches
                    ) == 1
                ) {
                    $legislator['photo_url'] = 'https://apps.senate.virginia.gov/' . trim($matches[0]);
                    unset($matches);
                }

                /*
                 * Get legislator biography.
                 */
                if (
                    preg_match(
                        '/Biography(.+?)<div class="lrgblacktext">(.+?)<\/div>/s',
                        $html,
                        $matches
                    ) == 1
                ) {
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
                if (count($matches) == 5) {
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
            if (date('m') >= 11 && date('Y') % 2 == 1) {
                /*
                 * See if we know when the next session starts.
                 */
                $sql = 'SELECT date_started
						FROM sessions
						WHERE date_started > now()';
                $stmt = $GLOBALS['db']->prepare($sql);
                $stmt->execute();
                $session = $stmt->fetch(PDO::FETCH_OBJ);
                if (count($session) > 0) {
                    $legislator['date_started'] = $session->date_started;
                }

                /*
                 * If we don't know when the next session starts, go with January 1.
                 */
                else {
                    $legislator['date_started'] = date('Y') + 1 . '-01-01';
                }
            }

            /*
             * If this is not post-election, just make the date yesterday.
             */
            else {
                $legislator['date_started'] = date('Y-m-d', strtotime('-1 day'));
            }

            /*
             * Format senator's shortname.
             */
            preg_match_all('([A-Za-z-]+)', $shortname, $matches);
            $legislator['shortname'] = '';
            $i = 0;
            while ($i + 1 < count($matches[0])) {
                $legislator['shortname'] .= $matches[0][$i][0];
                $i++;
            }
            $tmp = explode(', ', $legislator['name']);
            $legislator['shortname'] .= $tmp[0];
            $legislator['shortname'] = strtolower($legislator['shortname']);

            /*
             * Turn district number into a district ID
             */
            $district = new District();
            $d = $district->info('senate', $legislator['district_number']);
            $legislator['district_id'] = $d['id'];
            $district = null;

            /*
             * We no longer need the district number.
             */
            unset($legislator['district_number']);
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
        if (!empty($legislator['address_district'])) {
            $location->address = $legislator['address_district'];
        } elseif (!empty($legislator['place'])) {
            $location->address = $legislator['place'] . ', VA';
        }
        if (!empty($legislator['place']) && $location->get_coordinates($legislator['place']) != false) {
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
        if (!empty($legislator['race'])) {
            if (array_key_exists($legislator['race'], $race_map)) {
                $legislator['race'] = $race_map[$legislator['race']];
            }
            // If multiple races are listed, don't record anything
            elseif (stristr($legislator['race'], ',')) {
                $legislator['race'] = '';
            }
        }

        /*
         * Drop any array elements with blank contents.
         */
        foreach ($legislator as $key => $value) {
            if (!empty($value)) {
                $newLegislator[$key] = $value;
            }
        }

        return $legislator;
    }

    /**
     * Retrieve legislator data from the General Assembly public API.
     *
     * @param string $chamber Chamber identifier (`house` or `senate`).
     * @param string $lis_id  Legislator LIS identifier.
     *
     * @return array|false Parsed legislator data or false on failure.
     */
    public function fetch_legislator_data_api($chamber, $lis_id)
    {
        if (empty($chamber) || empty($lis_id)) {
            return false;
        }

        $chamber_normalized = strtolower($chamber);
        $member_number = $this->normalize_member_number($chamber_normalized, $lis_id);
        if ($member_number === false) {
            $this->log->put('Could not normalize member number for ' . $lis_id, 5);
            return false;
        }

        $session_code = '20' . SESSION_LIS_ID;

        $member = $this->fetch_member_record($member_number, $session_code);
        if ($member === false) {
            $this->log->put('No API member data returned for ' . $member_number, 5);
            return false;
        }

        $contact_details = $this->extract_contact_details_from_api(
            $this->lis_api_request(
                '/Member/api/getmemberscontactinformationlistasync',
                [
                    'sessionCode' => $session_code
                ]
            ),
            $member_number
        );

        return $this->map_member_to_legislator(
            $member,
            $contact_details,
            $member_number,
            $chamber_normalized
        );
    }

    /**
     * Retrieve the list of active members from the LIS API.
     *
     * @param string|null $chamber Optional chamber filter (`house` or `senate`).
     *
     * @return array List of normalized legislator records.
     */
    public function fetch_active_members($chamber = null)
    {
        $session_code = '20' . SESSION_LIS_ID;
        $query = [
            'sessionCode' => $session_code
        ];

        if (!empty($chamber)) {
            $normalized = strtolower($chamber);
            if ($normalized === 'house' || $normalized === 'h') {
                $query['chamberCode'] = 'H';
            } elseif ($normalized === 'senate' || $normalized === 's') {
                $query['chamberCode'] = 'S';
            } else {
                return [];
            }
        }

        $response = $this->lis_api_request('/Member/api/getactivemembersasync', $query);
        $members = $this->extract_members_from_response($response);
        if (empty($members)) {
            return [];
        }

        $contacts_response = $this->lis_api_request(
            '/Member/api/getmemberscontactinformationlistasync',
            [
                'sessionCode' => $session_code
            ]
        );

        $legislators = [];
        foreach ($members as $member) {
            if (!is_array($member) || empty($member['MemberNumber'])) {
                continue;
            }

            $contact_details = $this->extract_contact_details_from_api(
                $contacts_response,
                $member['MemberNumber']
            );

            $member_chamber_code = strtoupper($member['ChamberCode'] ?? '');
            $member_chamber = ($member_chamber_code === 'S') ? 'senate' : 'house';
            $member_number_normalized = $this->normalize_member_number(
                $member_chamber,
                $member['MemberNumber'] ?? ''
            );
            if ($member_number_normalized === false) {
                continue;
            }

            $legislator = $this->map_member_to_legislator(
                $member,
                $contact_details,
                $member_number_normalized,
                $member_chamber
            );
            if (!empty($legislator)) {
                $legislators[] = $legislator;
            }
        }

        return $legislators;
    }

    /**
     * Fetch a single member record from the LIS API.
     *
     * @param string $member_number LIS member number.
     * @param string $session_code  Session code (e.g. 2024).
     *
     * @return array|false Decoded member data or false on failure.
     */
    private function fetch_member_record($member_number, $session_code)
    {
        $response = $this->lis_api_request(
            '/Member/api/getmembersasync',
            [
                'memberNumber' => $member_number,
                'sessionCode' => $session_code
            ]
        );

        $member = $this->extract_member_from_response($response, $member_number);
        if ($member !== false) {
            return $member;
        }

        $response = $this->lis_api_request(
            '/Member/api/getactivemembersasync',
            [
                'sessionCode' => $session_code
            ]
        );

        return $this->extract_member_from_response($response, $member_number);
    }

    /**
     * Extract a specific member from an API response payload.
     *
     * @param array|string $response      Raw API response.
     * @param string        $member_number Member number to search for.
     *
     * @return array|false Matching member data or false when absent.
     */
    private function extract_member_from_response($response, $member_number)
    {
        $candidates = $this->extract_members_from_response($response);

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (
                isset($candidate['MemberNumber'])
                && strtoupper($candidate['MemberNumber']) === strtoupper($member_number)
            ) {
                return $candidate;
            }
        }

        return false;
    }

    /**
     * Issue an HTTP request to the LIS API.
     *
     * @param string $path  API path beginning with a slash.
     * @param array  $query Query parameters to append.
     *
     * @return array|false Decoded JSON response or false on failure.
     */
    private function lis_api_request($path, array $query = [])
    {
        $base_url = 'https://lis.virginia.gov';
        $query = array_filter($query, static function ($value) {
            return $value !== null && $value !== '';
        });

        $url = $base_url . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'WebAPIKey: ' . LIS_KEY,
            'Accept: application/json'
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            $this->log->put('LIS API request failed for ' . $url . ' with status ' . $status . ' error ' . $error, 5);
            return [];
        }

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->log->put('Invalid JSON returned from LIS API for ' . $url . ': ' . json_last_error_msg(), 5);
            return [];
        }

        if (isset($decoded['Success']) && $decoded['Success'] === false) {
            $this->log->put('LIS API request reported failure for ' . $url . ': ' . ($decoded['FailureMessage'] ?? ''), 5);
            return [];
        }

        if (isset($decoded['ListItems']) && is_array($decoded['ListItems'])) {
            return $decoded['ListItems'];
        }

        return $decoded;
    }

    /**
     * Normalize an LIS identifier into the numeric member number.
     *
     * @param string $chamber Chamber identifier (`house` or `senate`).
     * @param string $lis_id  Raw LIS identifier.
     *
     * @return string|false Normalized member number or false on failure.
     */
    private function normalize_member_number($chamber, $lis_id)
    {
        $digits = preg_replace('/[^0-9]/', '', $lis_id);
        if ($digits === '') {
            return false;
        }

        if (strtolower($chamber) === 'senate') {
            return 'S' . str_pad($digits, 4, '0', STR_PAD_LEFT);
        }

        return 'H' . str_pad($digits, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Extract the member list array from an API response.
     *
     * @param array|string $response Raw API response payload.
     *
     * @return array Members array (possibly empty).
     */
    private function extract_members_from_response($response)
    {
        if (!is_array($response)) {
            return [];
        }

        if (isset($response['Members']) && is_array($response['Members'])) {
            return $response['Members'];
        }

        if (isset($response['ListItems']) && is_array($response['ListItems'])) {
            return $response['ListItems'];
        }

        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        return [];
    }

    /**
     * Extract contact information for a specific member from an API response.
     *
     * @param array|string $response      Raw contact response payload.
     * @param string        $member_number Member number to match.
     *
     * @return array Structured contact information.
     */
    private function extract_contact_details_from_api($response, $member_number)
    {
        $contacts = [];
        if (is_array($response)) {
            if (isset($response['MemberContactInformationList']) && is_array($response['MemberContactInformationList'])) {
                $contacts = $response['MemberContactInformationList'];
            } elseif (isset($response['ListItems']) && is_array($response['ListItems'])) {
                $contacts = $response['ListItems'];
            } elseif (is_array($response) && isset($response[0])) {
                $contacts = $response;
            }
        }

        $contacts = array_filter(
            $contacts,
            static function ($contact) use ($member_number) {
                if (!is_array($contact)) {
                    return false;
                }
                return isset($contact['MemberNumber']) && strtoupper($contact['MemberNumber']) === strtoupper($member_number);
            }
        );

        $details = [
            'address_richmond' => null,
            'phone_richmond' => null,
            'address_district' => null,
            'phone_district' => null,
            'email' => null,
            'place' => null,
        ];

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $entries = [];
            if (isset($contact['ContactInformation']) && is_array($contact['ContactInformation'])) {
                $entries = $contact['ContactInformation'];
            } else {
                $entries[] = $contact;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $type = strtoupper($entry['ContactType'] ?? '');
                $address = $this->buildAddress($entry);
                $phone = $this->normalizePhone($entry['PhoneNumber'] ?? null);

                if ($this->isCapitolContactType($type)) {
                    if ($details['address_richmond'] === null) {
                        $details['address_richmond'] = $address;
                    }
                    if ($details['phone_richmond'] === null) {
                        $details['phone_richmond'] = $phone;
                    }
                } elseif ($this->isDistrictContactType($type)) {
                    if ($details['address_district'] === null) {
                        $details['address_district'] = $address;
                    }
                    if ($details['phone_district'] === null) {
                        $details['phone_district'] = $phone;
                    }
                    if ($details['place'] === null && !empty($entry['City'])) {
                        $details['place'] = $entry['City'];
                    }
                }

                if ($details['email'] === null && !empty($entry['EmailAddress'])) {
                    $details['email'] = $entry['EmailAddress'];
                }
            }

            if ($details['phone_richmond'] === null && !empty($contact['GABPhoneNumber'])) {
                $details['phone_richmond'] = $this->normalizePhone($contact['GABPhoneNumber']);
            }
            if ($details['email'] === null && !empty($contact['GABEmailAddress'])) {
                $details['email'] = $contact['GABEmailAddress'];
            }
            if ($details['address_richmond'] === null && !empty($contact['RoomNumber'])) {
                $details['address_richmond'] = 'Room ' . trim($contact['RoomNumber']) . ', General Assembly Building, Richmond, VA';
            }
        }

        return array_filter($details, static function ($value) {
            if (is_string($value)) {
                return trim($value) !== '';
            }
            return $value !== null;
        });
    }

    /**
     * Map LIS member and contact data into the local legislator structure.
     *
     * @param array  $member          Member record from the LIS API.
     * @param array  $contact_details Normalized contact details.
     * @param string $member_number   Normalized member number.
     * @param string $chamber         Chamber identifier.
     *
     * @return array Normalized legislator payload.
     */
    private function map_member_to_legislator(
        array $member,
        array $contact_details,
        string $member_number,
        string $chamber
    ) {
        $member_number = strtoupper($member_number);
        $chamber_normalized = ($chamber === 'senate') ? 'senate' : 'house';
        $party_code = strtoupper($member['PartyCode'] ?? '');

        $preferred_first_name = $this->determine_preferred_first_name(
            $member_number,
            $member,
            $chamber_normalized
        );
        $last_name = $this->extract_last_name($member);

        $name_formal = trim((string)($member['MemberDisplayName'] ?? $member['ListDisplayName'] ?? ''));
        if ($name_formal === '' && $preferred_first_name !== '' && $last_name !== '') {
            $name_formal = $preferred_first_name . ' ' . $last_name;
        }

        $name_last_first = $this->format_name_last_first_from_api($last_name, $preferred_first_name);
        $shortname = $this->build_shortname($preferred_first_name, $member, $last_name);

        $district_id = $this->resolve_district_internal_id($chamber_normalized, $member);
        $date_started = $this->format_service_begin_date($member['ServiceBeginDate'] ?? null);
        $lis_id = $this->format_member_lis_id($chamber_normalized, $member_number);

        $place = $contact_details['place'] ?? ($member['DistrictName'] ?? '');

        $legislator = [
            'lis_id' => $lis_id,
            'chamber' => $chamber_normalized,
            'name_formal' => $name_formal,
            'name' => $name_last_first,
            'name_formatted' => $this->format_name_formatted_from_api(
                $chamber_normalized,
                $preferred_first_name,
                $last_name,
                $party_code,
                $place
            ),
            'shortname' => $shortname,
            'district_id' => $district_id,
            'party' => $party_code,
            'date_started' => $date_started,
            'email' => $contact_details['email'] ?? ($member['GABEmailAddress'] ?? null),
            'address_richmond' => $contact_details['address_richmond'] ?? null,
            'phone_richmond' => $contact_details['phone_richmond'] ?? null,
            'address_district' => $contact_details['address_district'] ?? null,
            'phone_district' => $contact_details['phone_district'] ?? null,
            'place' => $contact_details['place'] ?? null,
            'photo_url' => $this->build_photo_url($member_number),
        ];

        $location = new Location();
        if (!empty($legislator['address_district'])) {
            $location->address = $legislator['address_district'];
        } elseif (!empty($legislator['place'])) {
            $location->address = $legislator['place'] . ', VA';
        }

        if (
            isset($location->address)
            && !empty($legislator['place'])
            && $location->get_coordinates() !== false
        ) {
            $legislator['latitude'] = round($location->latitude, 2);
            $legislator['longitude'] = round($location->longitude, 2);
        }

        return array_filter($legislator, function ($value) {
            if (is_string($value)) {
                return trim($value) !== '';
            }
            return $value !== null;
        });
    }

    /**
     * Determine the preferred first name for a member.
     *
     * @param string $member_number Member number identifier.
     * @param array  $member        Member data array.
     * @param string $chamber       Chamber identifier.
     *
     * @return string Preferred first name.
     */
    private function determine_preferred_first_name(string $member_number, array $member, string $chamber)
    {
        $cache_key = $member_number . ':' . $chamber;
        if (isset($this->preferredNameCache[$cache_key])) {
            return $this->preferredNameCache[$cache_key];
        }

        $display_name = (string)($member['MemberDisplayName'] ?? '');
        $preferred = '';
        if (preg_match('/"([^"]+)"/', $display_name, $matches) === 1) {
            $preferred = trim($matches[1]);
        }

        if ($preferred === '' && $chamber === 'house') {
            $preferred = (string)$this->fetch_house_preferred_name($member_number);
        }

        if ($preferred === '') {
            $list_display = (string)($member['ListDisplayName'] ?? '');
            $preferred = (string)$this->extract_first_name_from_list_display($list_display);
        }

        if ($preferred === '') {
            $parts = preg_split('/\s+/', trim($display_name));
            foreach ($parts as $part) {
                $token = $this->sanitize_name_token($part);
                if ($token === '' || preg_match('/^[A-Z]\.?$/', $token)) {
                    continue;
                }
                $preferred = $token;
                break;
            }
        }

        $preferred = $this->normalize_preferred_first_name($preferred, $member);
        return $this->preferredNameCache[$cache_key] = $preferred;
    }

    /**
     * Scrape the House member page to discover the preferred first name.
     *
     * @param string $member_number Member number identifier.
     *
     * @return string|null Preferred name or null if unavailable.
     */
    private function fetch_house_preferred_name(string $member_number)
    {
        $member_number = strtoupper($member_number);
        if (strpos($member_number, 'H') !== 0) {
            return null;
        }

        $url = 'https://virginiageneralassembly.gov/house/members/members.php?id=' . $member_number;
        $html = @file_get_contents($url);
        if ($html === false) {
            $this->log->put('Could not fetch House member page for ' . $member_number, 4);
            return null;
        }

        if (preg_match('/Preferred Name:\s*([^<]+)/i', $html, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract the first name token from a list display string.
     *
     * @param string $list_display Display string returned by the API.
     *
     * @return string|null Extracted first name or null when not found.
     */
    private function extract_first_name_from_list_display(string $list_display)
    {
        if ($list_display === '' || strpos($list_display, ',') === false) {
            return null;
        }

        $after_comma = trim(substr($list_display, strpos($list_display, ',') + 1));
        $parts = preg_split('/\s+/', $after_comma);
        foreach ($parts as $part) {
            $token = $this->sanitize_name_token($part);
            if ($token === '' || preg_match('/^[A-Z]\.?$/', $token)) {
                continue;
            }
            return $token;
        }

        return null;
    }

    /**
     * Normalise a name token by stripping punctuation and trimming whitespace.
     *
     * @param string $token Raw token extracted from a name string.
     *
     * @return string Sanitised token.
     */
    private function sanitize_name_token(string $token)
    {
        return trim($token, ' "\',.');
    }

    /**
     * Normalise the preferred first name using available member metadata.
     *
     * @param string $name   Preferred name candidate.
     * @param array  $member Member data array.
     *
     * @return string Normalised preferred first name.
     */
    private function normalize_preferred_first_name(string $name, array $member)
    {
        $name = trim($name);
        if ($name === '') {
            $display = (string)($member['MemberDisplayName'] ?? '');
            if ($display !== '') {
                $parts = preg_split('/\s+/', $display);
                $name = $this->sanitize_name_token($parts[0] ?? '');
            }
        }

        if ($name === '') {
            return $name;
        }

        $overrides = [
            'joshua' => 'Josh',
        ];

        $lower = strtolower($name);
        if (isset($overrides[$lower])) {
            return $overrides[$lower];
        }

        return $name;
    }

    /**
     * Build the legislator shortname from member data.
     *
     * @param string $preferred_first_name Preferred first name string.
     * @param array  $member                Member data array.
     * @param string $last_name             Last name string.
     *
     * @return string Generated shortname.
     */
    private function build_shortname(string $preferred_first_name, array $member, string $last_name)
    {
        $first_initial = '';
        $preferred_first_name = trim($preferred_first_name);
        if ($preferred_first_name !== '') {
            $first_initial = strtolower(mb_substr($preferred_first_name, 0, 1));
        } else {
            $display = trim((string)($member['MemberDisplayName'] ?? ''));
            if ($display !== '') {
                $parts = preg_split('/\s+/', $display);
                $token = $this->sanitize_name_token($parts[0] ?? '');
                if ($token !== '') {
                    $first_initial = strtolower($token[0]);
                }
            }
        }

        $middle_initial = '';
        $display_name = (string)($member['MemberDisplayName'] ?? '');
        $parts = preg_split('/\s+/', trim($display_name));
        if (count($parts) > 2) {
            for ($i = 1; $i < count($parts) - 1; $i++) {
                $token = $this->sanitize_name_token($parts[$i]);
                if ($token === '' || preg_match('/^[A-Z]\.?$/i', $token) !== 1) {
                    if ($token !== '' && preg_match('/^[A-Za-z]/', $token) === 1) {
                        $middle_initial = strtolower($token[0]);
                        break;
                    }
                    continue;
                }
                $middle_initial = strtolower($token[0]);
                break;
            }
        }

        $sanitized_last = strtolower(preg_replace('/[^A-Za-z-]+/', '', $last_name));
        return $first_initial . $middle_initial . $sanitized_last;
    }

    /**
     * Extract the last name from member data.
     *
     * @param array $member Member data array.
     *
     * @return string Last name string.
     */
    private function extract_last_name(array $member)
    {
        $list_display = (string)($member['ListDisplayName'] ?? '');
        if ($list_display !== '' && strpos($list_display, ',') !== false) {
            return trim(substr($list_display, 0, strpos($list_display, ',')));
        }

        $display_name = (string)($member['MemberDisplayName'] ?? '');
        $parts = preg_split('/\s+/', trim($display_name));
        if (count($parts) === 0) {
            return '';
        }

        return $this->sanitize_name_token(end($parts));
    }

    /**
     * Resolve the internal district ID for the given member.
     *
     * @param string $chamber Chamber identifier.
     * @param array  $member  Member data array.
     *
     * @return int|null Internal district ID or null when not found.
     */
    private function resolve_district_internal_id(string $chamber, array $member)
    {
        $district_number = null;
        if (!empty($member['DistrictID'])) {
            $district_number = (int)$member['DistrictID'];
        } elseif (!empty($member['DistrictName'])) {
            $district_number = $this->extract_district_number_from_name($member['DistrictName']);
        }

        if ($district_number === null) {
            return null;
        }

        $pdo = $this->getPdo();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id FROM districts WHERE date_ended IS NULL AND chamber = :chamber AND number = :number'
        );
        $stmt->bindValue(':chamber', $chamber);
        $stmt->bindValue(':number', $district_number, PDO::PARAM_INT);
        if ($stmt->execute() === false) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row !== false && isset($row['id'])) ? (int)$row['id'] : null;
    }

    /**
     * Format a service begin date value into Y-m-d format.
     *
     * @param mixed $value Raw date value from the API.
     *
     * @return string|null Normalised date string or null when unavailable.
     */
    private function format_service_begin_date($value)
    {
        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * Format a numeric member number into an LIS identifier.
     *
     * @param string $chamber       Chamber identifier.
     * @param string $member_number Member number digits.
     *
     * @return string LIS identifier (e.g. H0001).
     */
    private function format_member_lis_id(string $chamber, string $member_number)
    {
        $digits = preg_replace('/[^0-9]/', '', $member_number);
        if ($digits === '') {
            return null;
        }

        if ($chamber === 'senate') {
            $trimmed = ltrim($digits, '0');
            return $trimmed === '' ? '0' : $trimmed;
        }

        return 'H' . str_pad($digits, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build the URL for a legislator photo given the member number.
     *
     * @param string $member_number Member number identifier.
     *
     * @return string Absolute photo URL.
     */
    private function build_photo_url(string $member_number)
    {
        $member_number = strtoupper(trim($member_number));
        if ($member_number === '') {
            return null;
        }

        return 'https://memdata.virginiageneralassembly.gov/images/display_image/' . $member_number;
    }

    /**
     * Lazily obtain a PDO connection for read/write operations.
     *
     * @return PDO|null Active PDO connection or null on failure.
     */
    private function getPdo()
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $database = new Database();
            $connection = $database->connect();
            if ($connection instanceof PDO) {
                $this->pdo = $connection;
                if (!isset($GLOBALS['dbh']) || !($GLOBALS['dbh'] instanceof PDO)) {
                    $GLOBALS['dbh'] = $connection;
                }
                return $this->pdo;
            }
        } catch (Exception $e) {
            $this->log->put('Database connection failed: ' . $e->getMessage(), 5);
        }

        return null;
    }

    /**
     * Format a name into "Last, First" form.
     *
     * @param string $last_name  Last name string.
     * @param string $first_name First name string.
     *
     * @return string Formatted name.
     */
    private function format_name_last_first_from_api($last_name, $first_name)
    {
        $last_name = trim((string)$last_name);
        $first_name = trim((string)$first_name);

        if ($last_name === '') {
            return $first_name;
        }
        if ($first_name === '') {
            return $last_name;
        }

        return $last_name . ', ' . $first_name;
    }

    /**
     * Build the formatted display name (e.g. "Del. Jane Doe (D-Richmond)").
     *
     * @param string      $chamber           Chamber identifier.
     * @param string      $first_name        First name string.
     * @param string      $last_name         Last name string.
     * @param string|null $party             Party code.
     * @param string|null $place_or_district Place name or district descriptor.
     *
     * @return string Formatted name string.
     */
    private function format_name_formatted_from_api(
        $chamber,
        $first_name,
        $last_name,
        $party,
        $place_or_district
    ) {
        $prefix = ($chamber === 'senate') ? 'Sen.' : 'Del.';
        $name = trim($first_name . ' ' . $last_name);
        if ($name === '') {
            return '';
        }

        $formatted = trim($prefix . ' ' . $name);

        $party = strtoupper(trim((string)$party));
        if ($party === '') {
            $party = '?';
        }

        $suffix = trim((string)$place_or_district);
        if ($suffix === '') {
            $digits = $this->extract_district_number_from_name($place_or_district);
            if ($digits !== null) {
                $suffix = $digits;
            }
        }

        $formatted .= ' (' . $party;
        if ($suffix !== '') {
            $formatted .= '-' . $suffix;
        }
        $formatted .= ')';

        return $formatted;
    }

    /**
     * Extract a numeric district identifier from a descriptive string.
     *
     * @param string $district_name District description string.
     *
     * @return int|null District number or null when not found.
     */
    private function extract_district_number_from_name($district_name)
    {
        $digits = preg_replace('/[^0-9]/', '', (string)$district_name);
        if ($digits === '') {
            return null;
        }

        return (int)$digits;
    }

    /**
     * Construct a single-line mailing address from a contact entry.
     *
     * @param array $contact Contact entry from the API.
     *
     * @return string|null Address string or null when insufficient data.
     */
    private function buildAddress(array $contact)
    {
        $segments = [];

        foreach (['Address1', 'Address2', 'Address3'] as $line) {
            if (!empty($contact[$line])) {
                $segments[] = trim($contact[$line]);
            }
        }

        $city_state = '';
        if (!empty($contact['City'])) {
            $city_state = $contact['City'];
        }
        if (!empty($contact['StateCode'])) {
            $city_state = trim($city_state . ', ' . $contact['StateCode'], ', ');
        }
        if ($city_state !== '') {
            $segments[] = $city_state;
        }

        if (!empty($contact['ZipCode'])) {
            $segments[] = trim($contact['ZipCode']);
        }

        if (empty($segments)) {
            return null;
        }

        return implode(', ', $segments);
    }

    /**
     * Normalise a phone number into (###) ###-#### format.
     *
     * @param string|null $phone Raw phone number string.
     *
     * @return string|null Normalised phone number or null when not parseable.
     */
    private function normalizePhone($phone)
    {
        if (empty($phone)) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
        }
        return $phone;
    }

    /**
     * Determine whether a contact type denotes Capitol contact information.
     *
     * @param string|null $type Contact type code.
     *
     * @return bool True when the contact represents Capitol details.
     */
    private function isCapitolContactType($type)
    {
        $type = strtoupper($type);
        $capitol_types = ['GA', 'GA OFFICE', 'GENERAL ASSEMBLY', 'SESSION', 'CAPITOL', 'LEGISLATIVE', 'RICHMOND'];
        foreach ($capitol_types as $candidate) {
            if (str_contains($type, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine whether a contact type denotes district contact information.
     *
     * @param string|null $type Contact type code.
     *
     * @return bool True when the contact represents district details.
     */
    private function isDistrictContactType($type)
    {
        $type = strtoupper($type);
        $district_types = ['DISTRICT', 'MAILING', 'LOCAL', 'HOME', 'OFFICE'];
        foreach ($district_types as $candidate) {
            if (str_contains($type, $candidate)) {
                return true;
            }
        }
        return false;
    }
}
