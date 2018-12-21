<?php

# For functions pertaining to geolocation of individuals and legislators.
class Location
{

    # When given an address (whether a ZIP code alone or a complete street address), returns the
    # lat/lon pair for that location. Results return as an array, not an object.
    public function get_coordinates()
    {

        # If we've got a full address, join its components into a single string.
        if (isset($this->street, $this->city, $this->zip))
        {
            $q = $this->street . ', ' . $this->city . ', VA ' . $this->zip;
        }
        elseif (isset($this->zip))
        {
            $q = $this->zip;
        }

        # Assemble our URL, instructing Yahoo to return a serialized PHP array.
        $url = 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?address=' . urlencode($q) . '&benchmark=9&format=json';

        # Retrieve the resulting serialized array.
        $response = get_content($url);

        # If the response doesn't come, return false.
        if ($response === FALSE)
        {
            return FALSE;
        }

        # Turn the JSON into a PHP array.
        $response = json_decode($response, TRUE);
        $response = $response['result'];

        # If ther are no address matches, bail.
        if (count($response['addressMatches']) == 0)
        {
            return FALSE;
        }

        # In theory there could be multiple responses, but we don't have any method of dealing with
        # that outcome, so we simply return the first match and hope it's right. We save them to the
        # object namespace, too, since this function is generally followed by coords_to_districts().
        $this->latitude = $response['addressMatches'][0]['coordinates']['y'];
        $this->longitude = $response['addressMatches'][0]['coordinates']['x'];
        $coordinates = array();
        $coordinates['latitude'] = $this->latitude;
        $coordinates['longitude'] = $this->longitude;
        return $coordinates;
    }

    # Convert coordinates into district IDs.
    public function coords_to_districts()
    {
        if (!isset($this->latitude) || !isset($this->longitude))
        {
            return FALSE;
        }

        # Assemble our URL.
        $url = 'https://openstates.org/api/v1/legislators/geo/?apikey=' . OPENSTATES_KEY . '&lat='
            . $this->latitude . '&long=' . $this->longitude;

        # Retrieve the resulting JSON..
        $district = get_content($url);

        # If we couldn't retrieve that content, then bail.
        if ($district === FALSE)
        {
            return FALSE;
        }

        $district = json_decode($district, TRUE);

        # If this isn't an array with two elements (one for each legislator), bail.
        if (count($district) != 2)
        {
            return FALSE;
        }

        $result = new stdClass();
        foreach ($district as $legislator)
        {

            # If it's the house.
            if ($legislator['chamber'] == 'lower')
            {
                $result->house = district_to_id($legislator['district'], 'house');
            }
            # Else if it's the senate.
            elseif ($legislator['chamber'] == 'upper')
            {
                $result->senate = district_to_id($legislator['district'], 'senate');
            }
        }

        if (count((array)$result) == 0)
        {
            return false;
        }

        return $result;
    }
}
