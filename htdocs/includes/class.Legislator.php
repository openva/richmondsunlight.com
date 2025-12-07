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
                LEFT JOIN terms
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
                    terms.latitude,
                    terms.longitude,
                    terms.place,
                    terms.lis_id
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
        $legislator = array_map('stripslashes', $legislator);

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
                    committee_members.representative_id = ' . $legislator['id'] . ' AND
                    (committee_members.date_ended IS NULL
                    OR
                    committee_members.date_ended > now())';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0) {
            while ($committee = mysqli_fetch_assoc($result)) {
                # Clean it up.
                $committee = array_map('stripslashes', $committee);

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
}
