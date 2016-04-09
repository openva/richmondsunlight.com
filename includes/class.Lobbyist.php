<?php

/**
 * Lobbyist records.
 */
class Lobbyist
{
	
	/**
	 * Get a single lobbying registration. (Each lobbyist registers once per year for each client,
	 * and this is a single instance of a single registration for a single client.)
	 */
	function get_registration()
	{
		
		/*
		 * We must have a registration ID.
		 */
		if (!isset($this->sc_id))
		{
			return FALSE;
		}
	
		$sql = 'SELECT name, sc_id, id_hash, principal, address, phone, year,
				statement, date_registered, organization
				FROM lobbyists
				WHERE sc_id = "' . $this->sc_id . '"';
		
		
		/*
		 * Generate URL for the JSON file.
		 */
		$lobbyist->json_url = 'http://openva.com/lobbyists/lobbyists/' . $lobbyist->sc_id . '.json';
		
		/*
		 * Generate the URL for the official website.
		 */
		$lobbyist->sc_url = 'https://solutions.virginia.gov/Lobbyist/Reports/LobbyistSearch/Detail?contactId='
			. $lobbyist->sc_id;
		
	}
	
	
	/**
	 * Get all records for a single lobbyist.
	 */
	function get_lobbyist()
	{
	
		/*
		 * Get the basics about this lobbyist.
		 */
		$sql = 'SELECT name, sc_id, id_hash, principal, address, phone
				FROM lobbyists
				WHERE id_hash = "' . $this->id_hash . '"
				LIMIT 1';
		$result = mysql_query($sql);
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		$this->lobbyist = mysql_fetch_object($result);
		
		/*
		 * List all principals for whom this lobbyist registered.
		 */
		$sql = 'SELECT principal, year, statement, date_registered
				FROM lobbyists
				WHERE id_hash = "' . $this->id_hash . '"
				ORDER BY principal ASC';
		$result = mysql_query($sql);
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		$i=0;
		$this->lobbyist->principals = new stdClass();
		while ($tmp = mysql_fetch_object($result))
		{
			$this->lobbyist->principals->{$i} = $tmp;
			$i++;
		}
		
		return TRUE;
		
	}
	
	/**
	 * Get information about a single principal, by MD5.
	 */
	function get_principal()
	{
		
		if (!isset($this->principal_hash))
		{
			return FALSE;
		}
		
		/*
		 * Get the principal's name.
		 */
		$sql = 'SELECT principal
				FROM lobbyists
				WHERE principal_hash = "' . $this->principal_hash . '"';
		$result = mysql_query($sql);
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		$tmp = mysql_fetch_object($result);
		$this->name = $tmp->principal;
	
		/*
		 * Get the basics about these lobbyists.
		 */
		$sql = 'SELECT name, sc_id, id_hash, principal, principal_hash, address, phone,
		
					(SELECT DATE_FORMAT(date_registered, "%Y")
					FROM lobbyists AS l2
					WHERE l2.id_hash = lobbyists.id_hash
					ORDER BY date_registered ASC
					LIMIT 1) AS year_start,
					
					(SELECT DATE_FORMAT(date_registered, "%Y")
					FROM lobbyists AS l2
					WHERE l2.id_hash = lobbyists.id_hash
					ORDER BY date_registered DESC
					LIMIT 1) AS year_end
					
				FROM lobbyists
				WHERE principal_hash = "' . $this->principal_hash . '"
				GROUP BY name
				ORDER BY name ASC';
		$result = mysql_query($sql);
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		$i=0;
		$this->lobbyists = new stdClass();
		while ($tmp = mysql_fetch_object($result))
		{
			$this->lobbyists->{$i} = $tmp;
			$i++;
		}
		
		return TRUE;
	}
	
	
	/**
	 * List all principals.
	 */
	function list_principals()
	{
		
		// list all principals
		$sql = 'SELECT DISTINCT principal
				FROM lobbyists
				WHERE year = "' . $this->year . '"';
	
	}
	
	/**
	 * List all registrations for a given year.
	 */
	function list_year()
	{
		
		if (!isset($this->year))
		{
			return FALSE;
		}
		
		$sql = 'SELECT name, sc_id, id_hash, principal, principal_hash, statement, date_registered
				FROM lobbyists
				WHERE year = "' . $this->year . '"';
		$result = mysql_query($sql);
		if ($result === FALSE)
		{
			return FALSE;
		}
		
		/*
		 * Iterate through all of the registrations.
		 */
		$i=0;
		$this->registrations = new stdClass();
		while ($registration = mysql_fetch_object($result))
		{
			$this->registrations->{$i} = $registration;
			$i++;
		}
		
		return TRUE;
				
	}
}
