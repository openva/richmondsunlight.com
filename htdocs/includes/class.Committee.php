<?php

class Committee
{
    /**
     * Return information about a single committee.
     */
    public function info()
    {

        if (( empty($this->name) || empty($this->chamber) ) && ( empty($this->shortname) || empty($this->chamber) ) && empty($this->id)) {
            return false;
        }

        $db = new Database();
        $db->connect_mysqli();

        /*
         * Select the basic committee information.
         */
        $sql = 'SELECT id, shortname, name, chamber, meeting_time, url
                FROM committees
                WHERE ';
        if (isset($this->id)) {
            $sql .= 'id = ' . $this->id;
        } elseif (isset($this->shortname)) {
            $sql .= 'shortname="' . $this->shortname . '"
                    AND chamber="' . $this->chamber . '"';
        } elseif (isset($this->name)) {
            $sql .= 'name="' . $this->name . '"
                    AND chamber="' . $this->chamber . '"';
        }

        $result = mysqli_query($GLOBALS['db'], $sql);

        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        $info = mysqli_fetch_assoc($result);

        foreach ($info as $name => $value) {
            $this->$name = $value;
        }

        return true;
    }

    /**
     * Return the list of members for a single committee, or for all committees.
     */
    public function members()
    {

        $db = new Database();
        $db->connect_mysqli();

        $sql = 'SELECT representatives.id, representatives.shortname,
                representatives.name_formatted AS name,
				representatives.name AS name_simple, committee_members.position,
				representatives.email, committee_members.committee_id
                FROM committee_members
				LEFT JOIN
				representatives
					ON committee_members.representative_id=representatives.id
                WHERE ';
        if (isset($this->id)) {
            $sql .= 'committee_members.committee_id=' . $this->id . ' AND';
        }
        $sql .= '(committee_members.date_ended > now() OR committee_members.date_ended IS NULL)
				AND (representatives.date_ended >= now() OR representatives.date_ended IS NULL)
				ORDER BY committee_members.position DESC, representatives.name ASC';
        $result = mysqli_query($GLOBALS['db'], $sql);

        if (mysqli_num_rows($result) == 0) {
            return false;
        }

        $this->members = array();

        while ($member = mysqli_fetch_assoc($result)) {
            $member['name_simple'] = pivot($member['name_simple']);
            $this->members[] = $member;
        }

        $this->members = array_map_multi('stripslashes', $this->members);

        return true;
    }

    /**
     * Return the ID of a committee, when provided with a chamber and a name.
     */
    public function get_id()
    {
        if (!isset($this->chamber) || !isset($this->name)) {
            return false;
        }

        $sql = 'SELECT id, shortname, name, chamber, meeting_time, url,
                LEVENSHTEIN("' . $this->name . '", name) AS distance
                FROM committees
                WHERE chamber="' . $this->chamber . '"
                ORDER BY distance DESC
                LIMIT 1';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0) {
            return false;
        }
        $committee = mysqli_fetch_assoc($result);
        $this->id = $committee['id'];
        return $this->id;
    } // end get_id()
}
