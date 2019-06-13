<?php

class Vote
{

    # Take an LIS ID, return a vote tally.
    public function get_aggregate()
    {

        # Make sure we've got the information that we need.
        if (empty($this->lis_id))
        {
            return FALSE;
        }

        if (empty($this->session_id) && empty($this->session_year))
        {
            return FALSE;
        }

        # Check that the data is clean.
        if (mb_strlen($this->lis_id) > 12)
        {
            return FALSE;
        }
        if (mb_strlen($this->session_id) > 4)
        {
            return FALSE;
        }
        if (mb_strlen($this->session_year) <> 4)
        {
            return FALSE;
        }

        $database = new Database;
        $database->connect_mysqli();

        # If we have a session year, but not a session ID, look up the session ID.
        if (empty($this->session_id) && !empty($this->session_year))
        {
            $sql = 'SELECT id
					FROM sessions
					WHERE year="' . $this->session_year . '"
					AND suffix IS NULL';
            $result = mysqli_query($GLOBALS['db'], $sql);
            if (mysqli_num_rows($result) == 0)
            {
                die('No such vote found.');
            }
            $session_info = mysqli_fetch_assoc($result);
            $this->session_id = $session_info['id'];
        }

        /*
         * Query the DB.
         */
        $sql = 'SELECT chamber, outcome, tally
				FROM votes
				WHERE lis_id="' . $this->lis_id . '"
				AND session_id = ' . $this->session_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0)
        {
            die('No such vote found.');
        }

        $vote = mysqli_fetch_assoc($result);
        $vote = array_map('stripslashes', $vote);
        return $vote;
    }

    /*
     * Get detailed information about how individual legislators voted.
     */
    public function get_detailed()
    {

        # Make sure we've got the information that we need.
        if (!isset($this->lis_id) || empty($this->lis_id))
        {
            return FALSE;
        }

        if (!isset($this->session_id) || empty($this->session_id))
        {
            return FALSE;
        }

        # Check that the data is clean.
        if (mb_strlen($this->lis_id) > 12)
        {
            return FALSE;
        }
        if (mb_strlen($this->session_id) > 3)
        {
            return FALSE;
        }

        $database = new Database;
        $database->connect_mysqli();

        // The following bit was commented out of the WHERE portion of this query:
        //
        // AND votes.session_id='.$bill['session_id'].'
        //
        // When bills survive until the following session, and then are voted on anew, they're odd,
        // because they exist twice in Richmond Sunlight. So we can't make the query unique by session
        // ID. OTOH, if LIS vote IDs aren't unique, this may prove to be problematic.
        $sql = 'SELECT representatives.name, representatives.shortname,
				representatives_votes.vote, representatives.party,
				representatives.chamber, representatives.address_district AS address,
				DATE_FORMAT(representatives.date_started, "%Y") AS started,
				districts.number AS district
				FROM votes
				LEFT JOIN representatives_votes
					ON votes.id = representatives_votes.vote_id
				LEFT JOIN representatives
					ON representatives_votes.representative_id = representatives.id
				LEFT JOIN districts
					ON representatives.district_id=districts.id
				WHERE votes.lis_id="' . $this->lis_id . '" AND votes.session_id="' . $this->session_id . '"
				ORDER BY vote ASC, name ASC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) < 1)
        {
            return FALSE;
        }

        # Store all of the resulting data in an array, since we have to reuse it a couple of times.
        $legislators = array();
        while ($legislator = mysqli_fetch_assoc($result))
        {
            $legislators[] = $legislator;
        }

        return $legislators;
    }
}
