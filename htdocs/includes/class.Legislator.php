<?php

class Legislator
{

	function getid($shortname)
	{
		if (!isset($shortname) || empty($shortname))
		{
			return FALSE;
		}
		
		$sql = 'SELECT id
				FROM representatives
				WHERE shortname="'.mysql_real_escape_string($shortname).'"';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			return FALSE;
		}
		$legislator = mysql_fetch_array($result);
		return $legislator['id'];
	} // end function "getid"
	
	function info($id)
	{
	
		if (!isset($id))
		{
			return FALSE;
		}
		
		/*
		 * Connect to Memcached.
		 */
		$mc = new Memcached();
		$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		
		/*
		 * If this legislator is cached in Memcached, retrieve it from there.
		 */
		$result = $mc->get('legislator-' . $id);
		if ($result !== FALSE)
		{
			return unserialize($result);
		}
		
		/*
		 * RETRIEVE THE LEGISLATOR'S INFO FROM THE DATABASE
		 */
		$sql = 'SELECT representatives.id, representatives.name, representatives.shortname,
				representatives.name_formatted, representatives.chamber,
				districts.number AS district, districts.id AS district_id,
				districts.description AS district_description, representatives.partisanship,
				DATE_FORMAT(representatives.date_started, "%M %Y") AS date_started,
				DATE_FORMAT(representatives.date_ended, "%M %Y") AS date_ended,
				DATE_FORMAT(representatives.date_started, "%Y") AS year_started,
				DATE_FORMAT(representatives.date_ended, "%Y") AS year_ended,
				representatives.party,
				representatives.bio, representatives.rss_url, representatives.twitter_rss_url AS twitter,
				(DATE_FORMAT(now(), "%Y") - DATE_FORMAT(representatives.birthday, "%Y") -
				(DATE_FORMAT(now(), "00-%m-%d") < DATE_FORMAT(representatives.birthday, "00-%m-%d")))
				AS age, representatives.address_district, representatives.address_richmond,
				representatives.phone_district, representatives.phone_richmond,
				representatives.race, representatives.sex, representatives.notes,
				representatives.email, representatives.url AS website,
				representatives.latitude, representatives.longitude,
					(SELECT total
					FROM representatives_fundraising
					WHERE representatives_fundraising.representative_id = representatives.id
					ORDER BY year DESC
					LIMIT 1) AS total_raised,
				representatives.contributions, representatives.place
				FROM representatives
				LEFT JOIN districts
					ON representatives.district_id = districts.id
				WHERE representatives.id=' . mysql_real_escape_string($id);
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0)
		{
			return FALSE;
		}
		$legislator = mysql_fetch_assoc($result);
		
		# Clean it up.
		$legislator = array_map('stripslashes', $legislator);
		
		# Convert some data.
		$legislator['suffix'] = '('.$legislator['party'].'-'.$legislator['place'].')';
		$legislator['name'] = pivot($legislator['name']);
		$legislator['cash_on_hand'] = '$'.number_format($legislator['cash_on_hand']);
		$legislator['address_district'] = ereg_replace('^(.*),(.*),(.*)$', '\\1<br />\\2, \\3', $legislator['address_district']);
		if ($legislator['chamber'] == 'house')
		{
			$legislator['prefix'] = 'Del.';
		}
		elseif ($legislator['chamber'] == 'senate')
		{
			$legislator['prefix'] = 'Sen.';
		}
		if (!empty($legislator['contributions']))
		{
			$legislator['contributions'] = unserialize($legislator['contributions']);
		}
		
		# Set the pronoun to use for this legislator.
		if ($legislator['sex'] == 'male')
		{
			$legislator['pronoun'] = 'he';
			$legislator['possessive'] = 'his';
		}
		elseif ($legislator['sex'] == 'female')
		{
			$legislator['pronoun'] = 'she';
			$legislator['possessive'] = 'her';
		}
		else
		{
			$legislator['pronoun'] = 'their';
		}
		
		# Set the full name of the legislator's party.
		if ($legislator['party'] == 'R')
		{
			$legislator['party_name'] = 'Republican';
		}
		elseif ($legislator['party'] == 'D')
		{
			$legislator['party_name'] = 'Democratic';
		}
		else
		{
			$legislator['party_name'] = 'Independent';
		}
		
		# Create a visually friendly version of the legislator's website URL.
		$legislator['website_name'] = parse_url($legislator['website'], PHP_URL_HOST);
		$legislator['website_name'] = str_replace('www.', '', $legislator['website_name']);

		# Then get the legislator's committee membership.
		$sql = 'SELECT committees.shortname, committees.name, committee_members.position
				FROM committees
				LEFT JOIN committee_members
					ON committees.id = committee_members.committee_id
				WHERE committee_members.representative_id = '.$legislator['id'].'
				AND (committee_members.date_ended IS NULL OR
					committee_members.date_ended > now())';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			while ($committee = mysql_fetch_array($result))
			{
				# Clean it up.
				$committee = array_map('stripslashes', $committee);
				
				# Append the committee membership data to the legislator array.
				$legislator['committees'][] = $committee;
			}
		}
		
		/*
		 * Cache this legislator in Memcached.
		 */
		$mc->set('legislator-' . $id, serialize($legislator), (60 * 60 * 24));
		
		return $legislator;
		
	} // end class "info"

} // end class "legislator"
