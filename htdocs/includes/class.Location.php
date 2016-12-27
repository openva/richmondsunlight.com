<?php

# For functions pertaining to geolocation of individuals and legislators.
class Location
{
	
	# When given an address (whether a ZIP code alone or a complete street address), returns the
	# lat/lon pair for that location. Results return as an array, not an object.
	function get_coordinates()
	{

		# If we've got a full address, join its components into a single string.
		if (isset($this->street) && isset($this->city) && isset($this->zip))
		{
			$q = $this->street.', '.$this->city.', VA '.$this->zip;
		}
		elseif (isset($this->zip))
		{
			$q = $this->zip;
		}
	
		# Assemble our URL, instructing Yahoo to return a serialized PHP array.
		$url = 'http://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($q) . '&sensor=false';
		
		# Retrieve the resulting serialized array.
		$coordinates = get_content($url);

		# If the response doesn't come, return false.
		if ($coordinates === FALSE)
		{
			return FALSE;
		}
		
		# Turn the JSON into a PHP array.
		$coordinates = json_decode($coordinates, TRUE);
		
		# If the array indicates that there was an error, or if the country is anything other than the
		# U.S., then return false.
		if ($coordinates['status'] != 'OK')
		{
			return FALSE;
		}
		
		# In theory there could be multiple responses, but we don't have any method of dealing with
		# that outcome, so we simply return the first match and hope it's right. We save them to the
		# object namespace, too, since this function is generally followed by coords_to_districts().
		$this->latitude = $coordinates['results'][0]['geometry']['location']['lat'];
		$this->longitude = $coordinates['results'][0]['geometry']['location']['lng'];
		return $coordinates['results'][0]['geometry']['location'];
		
	}

	# Convert coordinates into district IDs.
	function coords_to_districts()
	{
		if (!isset($this->latitude) || !isset($this->longitude))
		{
			return FALSE;
		}
		
		# Assemble our URL.
		$url = 'http://openstates.org/api/v1/legislators/geo/?apikey=' . OPENSTATES_KEY . '&lat='
			. $this->latitude . '&long=' . $this->longitude;

		# Retrieve the resulting XML.
		$district = get_content($url);
		
		# If we couldn't retrieve that content, then bail.
		if ($district === FALSE)
		{
			return FALSE;
		}
		
		# Turn the XML into an array.
		$district = json_decode($district);
		
		# If this isn't an array with two elements (one for each legislator), bail.
		if (count($district) != 2)
		{
			return FALSE;
		}

		$result = new stdClass();
		foreach ($district as $legislator)
		{
			# If it's the house.
			if ($legislator->chamber == 'lower')
			{
				$result->house = district_to_id($legislator->district, 'house');
			}
			# Else if it's the senate.
			elseif ($legislator->chamber == 'upper')
			{
				$result->senate = district_to_id($legislator->district, 'senate');
			}
		}
		
		return $result;
	}
}
