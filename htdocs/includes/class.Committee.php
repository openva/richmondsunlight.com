<?php

class Committee
{

	/**
	 * Return information about a single committee.
	 */
	function info()
	{

		if ( empty($this->shortname) || empty($this->chamber) )
		{
			return FALSE;
		}

		$db = new Database;
		$db->connect_old();
		
		/*
		 * Select the basic committee information.
		 */
		$sql = 'SELECT id, shortname, name, chamber, meeting_time, url
				FROM committees
				WHERE shortname="' . $this->shortname . '"
				AND chamber="' . $this->chamber . '"';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			return FALSE;
		}

		$info = mysql_fetch_assoc($result);

		foreach ($info as $name => $value)
		{
			$this->$name = $value;
		}

		return TRUE;

	}

	/*
	 * Return the list of members for a single committee.
	 */
	function members()
	{

		if (empty($this->id))
		{
			return FALSE;
		}

		$db = new Database;
		$db->connect_old();

		$sql = 'SELECT representatives.shortname, representatives.name_formatted AS name,
				representatives.name AS name_simple, committee_members.position,
				representatives.email
				FROM representatives
				LEFT JOIN
				committee_members
					ON representatives.id=committee_members.representative_id
				WHERE committee_members.committee_id=' . $this->id . '
				AND (committee_members.date_ended > now() OR committee_members.date_ended IS NULL)
				AND (representatives.date_ended >= now() OR representatives.date_ended IS NULL)
				ORDER BY committee_members.position DESC, representatives.name ASC';
		$result = mysql_query($sql);

		if (mysql_num_rows($result) == 0)
		{
			return FALSE;
		}

		$this->members = array();
		while ($member = mysql_fetch_assoc($result))
		{
			$member['name_simple'] = pivot($member['name_simple']);
			$this->members[] = $member;
		}

		$this->members = array_map_multi('stripslashes', $this->members);

		return TRUE;

	}

	/**
	 * Return the ID of a committee, when provided with a chamber and a name.
	 */
	function get_id()
	{
		
		if ( !isset($this->chamber) || !isset($this->name) )
		{
			return FALSE;
		}

		/*
		 * First, get a list of all committees' names and IDs.
		 */
		$sql = 'SELECT id, name
				FROM committees
				WHERE parent_id IS NULL
				AND chamber = "' . $this->chamber . '"';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			return FALSE;
		}

		$committees = array();
		while ($committee = mysql_fetch_array($result))
		{
			$committees[$committee{'id'}] = $committee['name'];
		}

		$shortest = -1;
		foreach ($committees as $id => $name)
		{

			$distance = levenshtein($this->name, $name);
			if ($distance === 0)
			{
				$closest = $id;
				$shortest = 0;
				break;
			}

			elseif ($distance <= $shortest || $shortest < 0)
			{
				$closest = $id;
				$shortest = $distance;
			}

		}

		$this->id = $closest;
		return $this->id;

	} // end get_id()

}
