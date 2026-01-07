<?php

/**
 * Provides lookup, enrichment, and related-data utilities for General Assembly bills.
 *
 * The class is named "Bill2" because naming it "Bill" conflicts with other legacy code paths that
 * power legislator pages.
 */
class Bill2
{
    public $id;
    public $javascript;
    public $term_pcres;
    public $text;
    public $changes;

    protected $text_hash;
    protected $related_bills;

    /**
     * @var PDO Database connection.
     */
    protected $db;

    /**
     * @var Memcached|null Cache connection.
     */
    protected $cache;

    /**
     * Constructor with optional dependency injection.
     *
     * For backward compatibility, if no arguments are provided, connections
     * are established lazily when needed.
     *
     * @param PDO|null       $db    Database connection.
     * @param Memcached|null $cache Cache connection.
     */
    public function __construct(?PDO $db = null, ?Memcached $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Get the database connection, establishing one if needed.
     *
     * @return PDO
     */
    protected function getDb(): PDO
    {
        if ($this->db === null) {
            $database = new Database();
            $this->db = $database->connect();
        }
        return $this->db;
    }

    /**
     * Get the cache connection, establishing one if needed.
     *
     * @return Memcached|null Returns null if caching is disabled.
     */
    protected function getCache(): ?Memcached
    {
        if ($this->cache === null && MEMCACHED_SERVER != '') {
            $this->cache = new Memcached();
            $this->cache->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        }
        return $this->cache;
    }

    /**
     * Resolve a bill's internal identifier for a given session year and bill number.
     *
     * @param int|string $year   Four-digit session year (e.g. 2024).
     * @param string     $number Bill number (e.g. HB1 or SB250).
     *
     * @return int|false Bill ID when found, or false if the inputs are invalid or no match exists.
     */
    public function getid($year, $number)
    {

        # Make sure we've got the information that we need.
        if (empty($number) || !is_string($number)) {
            return false;
        }
        if (empty($year)) {
            return false;
        }

        # Normalize inputs.
        $year = (int) $year;
        $number = trim($number);

        # Check that the data is clean.
        if ($year < 1900 || $year > 2100) {
            return false;
        }
        if (mb_strlen($number) > 7 || !preg_match('/^[a-zA-Z]{2}\d+$/i', $number)) {
            return false;
        }
        $number = mb_strtolower($number);

        /*
         * If this bill is from the present year, try to retrieve the bill ID from Memcached.
         */
        if ($year == SESSION_YEAR) {
            $cache = $this->getCache();
            if ($cache !== null) {
                $result = $cache->get('bill-' . $number);
                if ($cache->getResultCode() == Memcached::RES_SUCCESS) {
                    return $result;
                }
            }
        }

        /*
         * Query the DB.
         */
        $sql = 'SELECT bills.id
                FROM bills
                LEFT JOIN sessions ON bills.session_id = sessions.id
                WHERE bills.number = :number AND sessions.year = :year
                ORDER BY sessions.date_started DESC
                LIMIT 1';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['number' => $number, 'year' => $year]);
        $bill = $stmt->fetch();

        if ($bill === false) {
            return false;
        }

        return $bill['id'];
    }

    /**
     * Retrieve a fully populated bill record for the current instance.
     *
     * @return array|false Associative array of bill data, or false when the bill is unavailable.
     */
    public function info()
    {
        if (!isset($this->id)) {
            return false;
        }

        $id = (int) $this->id;
        if ($id <= 0) {
            return false;
        }

        // Check cache first
        $cache = $this->getCache();
        if ($cache !== null) {
            $cached = $cache->get('bill-' . $id);
            if ($cache->getResultCode() == Memcached::RES_SUCCESS) {
                return unserialize($cached);
            }
        }

        // Fetch core bill data
        $bill = $this->fetchBasicInfo($id);
        if ($bill === false) {
            return false;
        }

        // Enrich with computed fields
        $this->enrichBillData($bill);

        // Fetch related data
        $this->fetchCopatrons($bill);
        $this->fetchTags($bill);
        $this->fetchStatusHistory($bill);
        $this->fetchTextVersions($bill);
        $this->fetchPlaces($bill);
        $this->fetchDuplicates($bill);

        // Fetch related bills
        $this->related($bill);
        $bill['related'] = $this->related_bills;

        // Cache the result
        $this->cacheResult($id, $bill, $cache);

        return $bill;
    }

    /**
     * Fetch the core bill record from the database.
     *
     * @param int $id Bill ID.
     *
     * @return array|false Bill data array or false if not found.
     */
    private function fetchBasicInfo(int $id)
    {
        $sql = 'SELECT
                    bills.id,
                    bills.number,
                    bills.session_id,
                    bills.chamber,
                    bills.catch_line,
                    bills.chief_patron_id,
                    bills.summary,
                    bills.summary_hash,
                    bills.full_text,
                    bills.notes,
                    bills.status,
                    bills.date_introduced,
                    bills.outcome,
                    bills2.number AS incorporated_into,
                    bills.copatrons AS copatron_count,
                    representatives.name AS patron,
                    districts.number AS patron_district,
                    sessions.year,
                    sessions.lis_id AS session_lis_id,
                    representatives.party AS patron_party,
                    representatives.chamber AS patron_chamber,
                    representatives.shortname AS patron_shortname,
                    representatives.place AS patron_place,
                    DATE_FORMAT(representatives.date_started, "%Y") AS patron_started,
                    representatives.name_formatted as patron_name_formatted,
                    representatives.address_district AS patron_address,
                    committees.name AS committee,
                    committees.shortname AS committee_shortname,
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
                WHERE bills.id = :id';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Add computed fields to the bill data.
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function enrichBillData(array &$bill): void
    {
        $bill['word_count'] = str_word_count($bill['full_text']);
        $bill['patron_suffix'] = '(' . $bill['patron_party'] . '-' . $bill['patron_place'] . ')';

        if ($bill['patron_chamber'] == 'house') {
            $bill['patron_prefix'] = 'Del.';
        } elseif ($bill['patron_chamber'] == 'senate') {
            $bill['patron_prefix'] = 'Sen.';
        }

        $bill['url'] = 'https://www.richmondsunlight.com/bill/' . $bill['year'] . '/'
            . mb_strtolower($bill['number']) . '/';

        // Flag as bill or resolution
        $prefix = preg_replace('/[0-9]/', '', $bill['number']);
        $bill['type'] = in_array($prefix, ['sr', 'hr', 'hj', 'sj']) ? 'resolution' : 'bill';
    }

    /**
     * Fetch copatrons for a bill.
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function fetchCopatrons(array &$bill): void
    {
        if ($bill['copatron_count'] <= 0) {
            return;
        }

        $sql = 'SELECT
                    representatives.shortname,
                    representatives.name_formatted,
                    representatives.partisanship
                FROM bills_copatrons
                LEFT JOIN representatives
                    ON bills_copatrons.legislator_id=representatives.id
                WHERE bills_copatrons.bill_id = :bill_id
                ORDER BY
                    representatives.chamber ASC,
                    representatives.name ASC';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['bill_id' => $bill['id']]);

        while ($copatron = $stmt->fetch()) {
            $bill['copatron'][] = $copatron;
        }
    }

    /**
     * Fetch tags for a bill.
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function fetchTags(array &$bill): void
    {
        $sql = 'SELECT id, tag FROM tags WHERE bill_id = :bill_id';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['bill_id' => $bill['id']]);
        $tags = $stmt->fetchAll();

        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                $bill['tags'][$tag['id']] = $tag['tag'];
            }
        }
    }

    /**
     * Fetch status history for a bill.
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function fetchStatusHistory(array &$bill): void
    {
        $sql = 'SELECT
                    bills_status.status,
                    bills_status.translation,
                    DATE_FORMAT(bills_status.date, "%m/%d/%Y") AS date,
                    DATE_FORMAT(bills_status.date, "%Y-%m-%d") AS date_raw,
                    bills_status.lis_vote_id,
                    votes.total AS vote_count
                FROM bills_status
                LEFT JOIN votes
                    ON bills_status.lis_vote_id = votes.lis_id
                    AND bills_status.session_id=votes.session_id
                WHERE bills_status.bill_id = :bill_id
                ORDER BY
                    date_raw DESC,
                    bills_status.id DESC';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['bill_id' => $bill['id']]);
        $status_history = $stmt->fetchAll();

        if (count($status_history) > 0) {
            $bill['status_history'] = $status_history;
        }
    }

    /**
     * Fetch text versions (PDFs) for a bill.
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function fetchTextVersions(array &$bill): void
    {
        $sql = 'SELECT
                    date_introduced AS date,
                    number,
                    pdf_url
                FROM bills_full_text
                WHERE bill_id = :bill_id
                ORDER BY date_introduced ASC';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['bill_id' => $bill['id']]);
        $text_versions = $stmt->fetchAll();

        if (count($text_versions) > 0) {
            $bill['text'] = [];
            foreach ($text_versions as $version) {
                if (empty($version['pdf_url'])) {
                    unset($version['pdf_url']);
                }
                $bill['text'][] = $version;
            }
        }
    }

    /**
     * Fetch place names mentioned in a bill.
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function fetchPlaces(array &$bill): void
    {
        $sql = 'SELECT
                    placename AS name,
                    latitude,
                    longitude
                FROM bills_places
                WHERE bill_id = :bill_id';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['bill_id' => $bill['id']]);
        $places = $stmt->fetchAll();

        if (count($places) > 0) {
            $bill['places'] = $places;
        }
    }

    /**
     * Fetch duplicate bills (bills with the same summary).
     *
     * @param array &$bill Bill data array (modified in place).
     */
    private function fetchDuplicates(array &$bill): void
    {
        $sql = 'SELECT
                    bills.number,
                    bills.chamber,
                    bills.catch_line,
                    bills.status,
                    representatives.name AS patron,
                    sessions.year,
                    bills.date_introduced
                FROM bills
                LEFT JOIN representatives
                    ON bills.chief_patron_id = representatives.id
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id
                WHERE
                    bills.session_id = :session_id AND
                    bills.summary_hash = :summary_hash AND
                    bills.id != :bill_id
                ORDER BY
                    bills.date_introduced ASC,
                    bills.chamber DESC';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([
            'session_id' => $bill['session_id'],
            'summary_hash' => $bill['summary_hash'],
            'bill_id' => $bill['id']
        ]);
        $duplicates = $stmt->fetchAll();

        if (count($duplicates) > 0) {
            $bill['duplicates'] = $duplicates;
        }
    }

    /**
     * Cache the bill result in Memcached.
     *
     * @param int            $id    Bill ID.
     * @param array          $bill  Bill data array.
     * @param Memcached|null $cache Cache connection.
     */
    private function cacheResult(int $id, array $bill, ?Memcached $cache): void
    {
        if ($cache === null) {
            return;
        }

        // Cache this bill for one week
        $cache->set('bill-' . $id, serialize($bill), (60 * 60 * 24 * 7));

        // Cache the bill's number indefinitely if from this year
        if ($bill['year'] == SESSION_YEAR) {
            $cache->set('bill-' . $bill['number'], $bill['id']);
        }
    }

    /**
     * Build PCRE patterns for defined terms referenced within the bill text.
     *
     * @return bool True when term patterns are available, false if they cannot be determined.
     */
    public function get_terms()
    {

        /*
         * We must have a bill ID.
         */
        if (!isset($this->id)) {
            return false;
        }

        $bill_id = (int) $this->id;
        if ($bill_id <= 0) {
            return false;
        }

        /*
         * Get an array of all sections of the Code of Virginia mentioned in this bill.
         */
        $code_sections = bill_sections($bill_id);

        if ($code_sections !== false) {
            /*
             * We need to include the section number in Javascript, since our API request (on
             * hover over each term) relies on it.
             */
            $this->javascript = '<script>var section_number = "' . $code_sections[0]['section_number'] . '";</script>';

            /*
             * Check Memcached.
             */
            $cache = $this->getCache();
            if ($cache !== null) {
                $this->term_pcres = $cache->get('definitions-' . $bill_id);
                if ($cache->getResultCode() == Memcached::RES_SUCCESS) {
                    return true;
                }
            }

            /*
             * The terms aren't cached in Memcached, so get them from the Virginia Decoded API.
             */
            $url = 'https://vacode.org/api/dictionary/?key=' . VA_DECODED_KEY . '&section=';

            /*
             * Just use the first cited section of the code. It ain't fancy, but it kind of
             * works.
             */
            $url .= $code_sections[0]['section_number'];
            $terms = get_content(urldecode($url), 1);
            $terms = (array) json_decode($terms);
            if (count($terms) == 0) {
                return false;
            }

            /*
             * If we now have terms, put them to work.
             */
            if ($terms !== false) {
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
                foreach ($terms as $term) {
                    /*
                     * Step through each character in this word.
                     */
                    for ($i = 0; $i < mb_strlen($term); $i++) {
                        /*
                         * If there are any uppercase characters, then make this PCRE string case
                         * sensitive.
                         */
                        if ((ord($term[$i]) >= 65) && (ord($term[$i]) <= 90)) {
                            $term_pcres[] = '/\b' . $term . '(s?)\b(?![^<]*>)/';
                            $caps = true;
                            break;
                        }
                    }

                    /*
                     * If we have determined that this term does not contain capitalized letters, then
                     * create a case-insensitive PCRE string.
                     */
                    if (!isset($caps)) {
                        $term_pcres[] = '/\b' . $term . '(s?)\b(?![^<]*>)/i';
                    }

                    /*
                     * Unset our flag -- we don't want to have it set the next time through.
                     */
                    if (isset($caps)) {
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
            if ($cache !== null) {
                $cache->set('definitions-' . $bill_id, $this->term_pcres);
            }

            return true;
        }

        return false;
    } // end method get_Terms


    /**
     * Generate a structured list of textual changes for the bill text.
     *
     * @todo Handle bills that amend multiple sections.
     *
     * @return array|false Array describing each change, or false when no changes are detected.
     */
    public function list_changes()
    {

        /*
         * We must have bill text.
         */
        if (!isset($this->text) || !is_string($this->text) || empty($this->text)) {
            return false;
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
        $cache = $this->getCache();
        if ($cache !== null) {
            $this->changes = $cache->get('bill-changes-' . $this->text_hash);
            if ($cache->getResultCode() == Memcached::RES_SUCCESS) {
                return $this->changes;
            }
        }
        unset($this->changes);

        /*
         * If the phrase "A BILL to amend and reenact" is found in the first 500 characters of this
         * bill, then it's amending an existing law.
         */
        if (mb_strpos(mb_substr($this->text, 0, 500), 'A BILL to amend and reenact') === false) {
            return false;
        }

        /*
         * Figure out where the law actually starts.
         */
        $parts = preg_split('/(:{1})(\s+)(§{1})(\s{1})/', $this->text);
        if (($parts === false) || count($parts) < 2) {
            return false;
        }
        $start = mb_strlen($parts[0]);

        /*
         * Hack off everything prior to the start of the proposed modifications.
         */
        $this->text = mb_substr($this->text, ($start + 10));
        $start = mb_strpos($this->text, "\n");
        $this->text = mb_substr($this->text, ($start + 1));

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
        $i = 0;
        foreach ($matches[2] as $key => $type) {
            /*
             * Verbosely specify what type of change this is.
             */
            if ($type == 'ins') {
                $type = 'insert';
            } else {
                $type = 'delete';
            }
            $this->changes[$i]['type'] = $type;

            /*
             * Include the text in question.
             */
            $this->changes[$i]['text'] = mb_convert_encoding($matches[3][$i], 'UTF-8', 'UTF-8');

            /*
             * Include the text and immediately precedes and follows the text in question.
             */
            $this->changes[$i]['preceded_by'] = mb_convert_encoding($matches[1][$i], 'UTF-8', 'UTF-8');
            $this->changes[$i]['followed_by'] = mb_convert_encoding($matches[5][$i], 'UTF-8', 'UTF-8');

            /*
             * Include both the original and the new text, which is to say that we apply the
             * transformation.
             */
            if ($type == 'insert') {
                $this->changes[$i]['original'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['followed_by'];
                $this->changes[$i]['new'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['text'] . $this->changes[$i]['followed_by'];
            } elseif ($type == 'delete') {
                $this->changes[$i]['original'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['text'] . $this->changes[$i]['followed_by'];
                $this->changes[$i]['new'] = $this->changes[$i]['preceded_by'] . $this->changes[$i]['followed_by'];
            }

            $this->changes[$i]['diff'] = mb_convert_encoding($matches[0][$i], 'UTF-8', 'UTF-8');

            /*
             * Indicate at what point we are in the text, which is useful as only an approximate
             * measure, to help provide guidance as to where this patch should be applied.
             */
            $this->changes[$i]['position'] = mb_strpos($this->text, $this->changes[$i]['diff']);

            $i++;
        }

        /*
         * If we failed to identify any changes.
         */
        if (count($this->changes) == 0) {
            $this->changes = false;
        }

        /*
         * Cache the results for three days.
         */
        if ($cache !== null) {
            $cache->set('bill-changes-' . $this->text_hash, $this->changes, (60 * 60 * 24 * 3));
        }

        return $this->changes;
    }

    /**
     * Retrieve fiscal impact statements associated with the bill.
     *
     * @return array|false Array of impact statements, or false when none exist.
     */
    public function impact_statements()
    {
        if (!isset($this->id)) {
            return false;
        }

        $id = (int) $this->id;
        if ($id <= 0) {
            return false;
        }

        $sql = 'SELECT lis_id, pdf_url, summary
                FROM fiscal_impact_statements
                WHERE bill_id = :bill_id';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['bill_id' => $id]);
        $impact_statements = $stmt->fetchAll();

        if (count($impact_statements) == 0) {
            return false;
        }

        return $impact_statements;
    }

    /**
     * Assemble a list of related bills using external and internal similarity checks.
     *
     * @param array $bill Bill data array containing tags, identifiers, and metadata.
     *
     * @return array|false Related bills when found, or false if the input is incomplete.
     */
    public function related($bill)
    {

        /*
         * Make sure that the whole bill object has been passed along.
         */
        if (
            !is_array($bill)
            || !isset($bill['tags'])
            || !is_array($bill['tags'])
            || empty($bill['tags'])
            || !isset($bill['id'])
            || !isset($bill['number'])
            || !isset($bill['summary_hash'])
            || !isset($bill['session_id'])
        ) {
            return false;
        }

        /*
         * If the bill is from the current session, query the recordedvote.org API.
         */
        if ($bill['session_id'] == SESSION_ID) {
            $result = $this->related_recordedvote($bill);
        }

        /*
         * If it's not from the current session, or if the recordedvote.org API returns false, then
         * query the internal related-bill system.
         */
        if ($bill['session_id'] != SESSION_ID || $result == false) {
            $this->related_internal($bill);
        }

        return $this->related_bills;
    }

    /**
     * Populate related bills via tag overlap within the internal database.
     *
     * @param array $bill Bill data array with tags and identifiers.
     *
     * @return bool True when the query executes, false otherwise.
     */
    private function related_internal($bill)
    {
        $db = $this->getDb();

        # Build placeholders for tags
        $tag_placeholders = [];
        $tag_params = [];
        $i = 0;
        foreach ($bill['tags'] as $tag) {
            $placeholder = ':tag' . $i;
            $tag_placeholders[] = 'tags2.tag = ' . $placeholder;
            $tag_params['tag' . $i] = $tag;
            $i++;
        }
        $tags_sql2 = implode(' OR ', $tag_placeholders);
        $tags_sql = str_replace('tags2', 'tags', $tags_sql2);

        # Display a list of related bills, by finding the bills that share the most tags with this
        # one.
        $sql = 'SELECT DISTINCT
                    bills.id,
                    bills.number,
                    bills.catch_line,
                    DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
                    committees.name,
                    sessions.year,
                    (
                        SELECT translation
                        FROM bills_status
                        WHERE bill_id=bills.id AND translation IS NOT NULL
                        ORDER BY date DESC, id DESC
                        LIMIT 1
                    ) AS status,
                    (
                        SELECT COUNT(*)
                        FROM bills AS bills2
                        LEFT JOIN tags AS tags2
                            ON bills2.id=tags2.bill_id
                        WHERE (' . $tags_sql2 . ')
                        AND bills2.id = bills.id
                    ) AS count
                FROM bills
                LEFT JOIN tags
                    ON bills.id=tags.bill_id
                LEFT JOIN sessions
                    ON bills.session_id=sessions.id
                LEFT JOIN committees
                    ON bills.last_committee_id = committees.id
                WHERE
                    (' . $tags_sql . ') AND
                    bills.id != :bill_id AND
                    bills.session_id = :session_id AND
                    bills.summary_hash != :summary_hash
                ORDER BY count DESC
                LIMIT 5';

        $stmt = $db->prepare($sql);

        # Merge tag params with other params
        $params = array_merge($tag_params, [
            'bill_id' => $bill['id'],
            'session_id' => $bill['session_id'],
            'summary_hash' => $bill['summary_hash']
        ]);

        $stmt->execute($params);
        $results = $stmt->fetchAll();

        if (count($results) > 0) {
            $this->related_bills = $results;
        }

        return true;
    }

    /**
     * Query the recordedvote.org API for similar legislation.
     *
     * @param array $bill Bill data array providing the number for the API request.
     *
     * @return bool True if related bills were retrieved, false on failure.
     */
    private function related_recordedvote($bill)
    {

        $url = 'https://api.recordedvote.org/v1/bill/similarity/' . $bill['number']
            . '?remove_duplicates=1';

        // Initialize cURL session
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $json = curl_exec($curl);

        if ($json === false) {
            curl_close($curl);
            return false;
        }

        curl_close($curl);

        $related_bills = json_decode($json, true);

        if ($related_bills === false) {
            return false;
        }

        $this->related_bills = [];
        $i = 0;
        foreach ($related_bills as $related_bill) {
            $tmp['number'] = strtolower($related_bill['bill_id']);
            $tmp['catch_line'] = $related_bill['bill_description'];
            $tmp['year'] = SESSION_YEAR;
            $this->related_bills[] = $tmp;
            $i++;
            if ($i == 5) {
                break;
            }
        }

        return true;
    }
} // end class "Bill2"
