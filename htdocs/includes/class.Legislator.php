<?php

/**
 * Handles lookup and detail retrieval for members of the General Assembly.
 */
class Legislator
{
    public $id;
    public $shortname;

    /**
     * List legislators constrained by a subset (e.g. current membership).
     *
     * @param string $subset Either `current` for active members or any other value for all.
     *
     * @return array|false Array of legislators keyed by columns, or false when none are found.
     */
    public function get_list($subset)
    {

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT
                    people.id,
                    terms.lis_id,
                    people.shortname,
                    people.name,
                    terms.name_formatted,
                    terms.chamber
                FROM people
                INNER JOIN terms
                    ON people.id = terms.person_id';
        if ($subset == 'current') {
            $sql .= ' WHERE terms.date_ended IS NULL OR terms.date_ended >= now()';
        }
        $sql .= ' ORDER BY people.name ASC';

        $result = mysqli_query($GLOBALS['db'], $sql);

        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        $legislators = array();
        while ($legislator = mysqli_fetch_assoc($result)) {
            $legislator['url'] = '/legislator/' . $legislator['shortname'];
            $legislators[] = $legislator;
        }

        return $legislators;
    } // end method "get_list"

    /**
     * Translate a legislator shortname into the internal representative ID.
     *
     * @param string $shortname Legislator shortname slug (e.g. john-doe).
     *
     * @return int|false Representative ID on success, or false when it cannot be resolved.
     */
    public function getid($shortname)
    {

        if (!isset($shortname) || empty($shortname)) {
            return false;
        }

        $database = new Database();
        $database->connect_mysqli();

        $sql = 'SELECT id
				FROM people
				WHERE shortname="' . mysqli_real_escape_string($GLOBALS['db'], $shortname) . '"';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }
        $legislator = mysqli_fetch_array($result);
        return $legislator['id'];
    } // end method "getid"

    /**
     * Retrieve detailed information about a legislator, with caching support.
     *
     * @param int|string $id Representative identifier.
     *
     * @return array|false Associative legislator data, or false if the ID is invalid or missing.
     */
    public function info($id)
    {

        if (!isset($id)) {
            return false;
        }

        /*
         * Connect to Memcached.
         */
        if (MEMCACHED_SERVER != '') {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);

            /*
            * If this legislator is cached in Memcached, retrieve it from there.
            */
            $result = $mc->get('legislator-' . $id);
            if ($result !== false) {
                return unserialize($result);
            }
        }

        $database = new Database();
        $database->connect_mysqli();

        /*
         * RETRIEVE THE LEGISLATOR'S INFO FROM THE DATABASE
         */
        $sql = 'SELECT
                    people.id,
                    people.name,
                    people.shortname,
				    terms.name_formatted,
                    terms.chamber,
                    districts.number AS district,
                    districts.id AS district_id,
                    districts.description AS district_description,
                    districts.boundaries AS district_boundaries,
                    terms.partisanship,
                    DATE_FORMAT(terms.date_started, "%M %Y") AS date_started,
                    DATE_FORMAT(terms.date_ended, "%M %Y") AS date_ended,
                    DATE_FORMAT(terms.date_started, "%Y") AS year_started,
                    DATE_FORMAT(terms.date_ended, "%Y") AS year_ended,
                    terms.party,
                    people.bio,
                    terms.rss_url,
                        (DATE_FORMAT(now(), "%Y") - DATE_FORMAT(people.birthday, "%Y") -
                        (DATE_FORMAT(now(), "00-%m-%d") < DATE_FORMAT(people.birthday, "00-%m-%d")))
                        AS age,
                    terms.address_district,
                    terms.address_richmond,
                    terms.phone_district,
                    terms.phone_richmond,
                    people.race,
                    people.sex,
                    terms.email,
                    terms.url AS website,
                    terms.place,
                    terms.lis_id,
                    terms.sbe_id
				FROM people
                LEFT JOIN terms
                    ON people.id = terms.person_id
				LEFT JOIN districts
					ON terms.district_id = districts.id
				WHERE people.id=' . mysqli_real_escape_string($GLOBALS['db'], $id);
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }
        $legislator = mysqli_fetch_assoc($result);

        # Clean it up.
        $legislator = array_map(fn($v) => $v === null ? null : stripslashes($v), $legislator);

        # Convert some data.
        $legislator['suffix'] = '(' . $legislator['party'] . '-' . $legislator['place'] . ')';
        $legislator['name'] = pivot($legislator['name']);
        $legislator['address_district'] = preg_replace('/^(.*),(.*),(.*)$/D', '\\1<br />\\2, \\3', $legislator['address_district']);
        if ($legislator['chamber'] == 'house') {
            $legislator['prefix'] = 'Del.';
        } elseif ($legislator['chamber'] == 'senate') {
            $legislator['prefix'] = 'Sen.';
        }

        # Set the pronoun to use for this legislator.
        if ($legislator['sex'] == 'male') {
            $legislator['pronoun'] = 'he';
            $legislator['possessive'] = 'his';
        } elseif ($legislator['sex'] == 'female') {
            $legislator['pronoun'] = 'she';
            $legislator['possessive'] = 'her';
        } else {
            $legislator['pronoun'] = 'they';
            $legislator['possessive'] = 'their';
        }

        # Set the full name of the legislator's party.
        if ($legislator['party'] == 'R') {
            $legislator['party_name'] = 'Republican';
        } elseif ($legislator['party'] == 'D') {
            $legislator['party_name'] = 'Democratic';
        } else {
            $legislator['party_name'] = 'Independent';
        }

        # Prepend the right prefix to the LIS ID
        if ($legislator['chamber'] == 'senate') {
            $legislator['lis_id'] = 'S' . $legislator['lis_id'];
        } elseif ($legislator['chamber'] == 'house') {
            $legislator['lis_id'] = 'H' . $legislator['lis_id'];
        }

        # Create a visually friendly version of the legislator's website URL.
        $legislator['website_name'] = parse_url($legislator['website'], PHP_URL_HOST);
        $legislator['website_name'] = str_replace('www.', '', $legislator['website_name']);

        # Then get the legislator's committee membership.
        $sql = 'SELECT
                    committees.shortname,
                    committees.name,
                    committee_members.position
				FROM committees
				LEFT JOIN committee_members
					ON committees.id = committee_members.committee_id
				WHERE
                    committees.date_ended IS NULL AND
                    committee_members.representative_id = ' . $legislator['id'] . ' AND
                    (committee_members.date_ended IS NULL
                    OR
                    committee_members.date_ended > now())';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0) {
            while ($committee = mysqli_fetch_assoc($result)) {
                # Clean it up.
                $committee = array_map(fn($v) => $v === null ? null : stripslashes($v), $committee);

                if (empty($committee['position'])) {
                    $committee['position'] = 'member';
                }

                # Append the committee membership data to the legislator array.
                $legislator['committees'][] = $committee;
            }
        }

        /*
         * Cache this legislator in Memcached.
         */
        if (MEMCACHED_SERVER != '') {
            $mc->set('legislator-' . $id, serialize($legislator), (60 * 60 * 24));
        }

        return $legislator;
    } // end method "info"

    /**
     * How long to cache a legislator's statistics, in seconds. A current legislator's change as
     * bills are introduced, tagged, and copatroned. A former legislator's hardly ever do.
     */
    public const STATISTICS_CACHE_CURRENT = 60 * 60;
    public const STATISTICS_CACHE_FORMER = 60 * 60 * 24 * 7;

    /**
     * Retrieve the statistics shown on a legislator's page, with caching support: how many of
     * their bills passed, the parties of the legislators they copatron bills with, and the
     * topics of their bills.
     *
     * @param int  $id           The legislator's people.id.
     * @param int  $batting_year The year for which to count how many bills passed.
     * @param bool $current      Whether they're serving now, which determines how long to cache.
     *
     * @return array{batting: array{passed: int, total: int}, copatroned: array<string, int>,
     *     copatrons: array<string, int>, pool: array<string, int>, tags: array<string, int>}
     *     The copatron counts are keyed by party. "copatroned" is the parties of the patrons of
     *     the bills they copatroned, "copatrons" the parties of the copatrons of their bills, and
     *     "pool" the parties of everybody who copatroned the bills they copatroned. Tags are
     *     keyed by tag, with the number of their bills with that tag, most first.
     */
    public static function statistics(int $id, int $batting_year, bool $current): array
    {
        $key = 'legislator-statistics-' . $id . '-' . $batting_year;

        if (MEMCACHED_SERVER != '') {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);

            $statistics = $mc->get($key);
            if ($mc->getResultCode() == Memcached::RES_SUCCESS && is_array($statistics)) {
                return $statistics;
            }
        }

        $database = new Database();
        $database->connect_mysqli();

        $statistics = [];

        # How many of their bills (not resolutions) passed.
        $sql = 'SELECT
                    COUNT(*) AS passed,
                    (
                        SELECT COUNT(*)
                        FROM bills
                        LEFT JOIN sessions
                            ON bills.session_id = sessions.id
                        WHERE sessions.year = ' . $batting_year . '
                        AND chief_patron_id = ' . $id . '
                        AND (bills.number LIKE "hb%" OR bills.number LIKE "sb%")
                    ) AS total
                FROM bills
                LEFT JOIN representatives
                    ON bills.chief_patron_id = representatives.id
                LEFT JOIN sessions
                    ON bills.session_id = sessions.id
                WHERE sessions.year = ' . $batting_year . '
                AND chief_patron_id = ' . $id . '
                AND (bills.outcome = "passed")
                AND (bills.number LIKE "hb%" OR bills.number LIKE "sb%")';
        $batting = mysqli_fetch_assoc(mysqli_query($GLOBALS['db'], $sql));
        $statistics['batting'] = [
            'passed' => (int) ($batting['passed'] ?? 0),
            'total' => (int) ($batting['total'] ?? 0),
        ];

        # The parties of the patrons of the bills that they copatroned.
        $statistics['copatroned'] = self::party_counts('SELECT representatives.party, COUNT(*) AS number
                FROM bills_copatrons
                LEFT JOIN bills
                    ON bills_copatrons.bill_id = bills.id
                LEFT JOIN representatives
                    ON bills.chief_patron_id = representatives.id
                WHERE bills_copatrons.legislator_id = ' . $id . '
                GROUP BY representatives.party');

        # The parties of the copatrons of their bills.
        $statistics['copatrons'] = self::party_counts('SELECT representatives.party, COUNT(*) AS number
                FROM bills_copatrons
                LEFT JOIN representatives
                    ON bills_copatrons.legislator_id = representatives.id
                WHERE bills_copatrons.bill_id IN
                    (SELECT id FROM bills WHERE chief_patron_id = ' . $id . ')
                GROUP BY representatives.party');

        # The parties of everybody who copatroned the bills that they copatroned.
        $statistics['pool'] = self::party_counts('SELECT representatives.party, COUNT(*) AS number
                FROM bills_copatrons
                LEFT JOIN representatives
                    ON bills_copatrons.legislator_id = representatives.id
                WHERE bills_copatrons.bill_id IN
                    (SELECT bill_id FROM bills_copatrons WHERE legislator_id = ' . $id . ')
                GROUP BY representatives.party');

        # The topics of their bills.
        $sql = 'SELECT COUNT(*) AS count, tags.tag
                FROM tags
                LEFT JOIN bills
                    ON tags.bill_id = bills.id
                LEFT JOIN representatives
                    ON bills.chief_patron_id = representatives.id
                WHERE representatives.id = ' . $id . '
                GROUP BY tags.tag
                ORDER BY count DESC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        $statistics['tags'] = [];
        while ($tag = mysqli_fetch_assoc($result)) {
            $statistics['tags'][stripslashes((string) $tag['tag'])] = (int) $tag['count'];
        }

        if (isset($mc)) {
            $mc->set($key, $statistics, $current ? self::STATISTICS_CACHE_CURRENT : self::STATISTICS_CACHE_FORMER);
        }

        return $statistics;
    } // end method "statistics"

    /**
     * Run a query that counts something by party.
     *
     * @param string $sql A query returning the columns "party" and "number."
     *
     * @return array<string, int> The counts, keyed by party.
     */
    private static function party_counts(string $sql): array
    {
        $counts = [];
        $result = mysqli_query($GLOBALS['db'], $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[(string) $row['party']] = (int) $row['number'];
        }

        return $counts;
    }
}
