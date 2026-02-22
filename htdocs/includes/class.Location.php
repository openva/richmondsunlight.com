<?php

/**
 * Provides geolocation utilities for addresses and coordinates within Virginia.
 */
class Location
{
    public $street;
    public $city;
    public $zip;
    public $address;
    public $latitude;
    public $longitude;

    /**
     * Resolve the latitude/longitude pair for the instance's address components.
     *
     * @return array|false Associative array with `latitude` and `longitude`, or false on failure.
     */
    public function get_coordinates()
    {

        # If we've got a full address, join its components into a single string.
        if (isset($this->street, $this->city, $this->zip)) {
            $q = '?SingleLine=' . urlencode($this->street . ', ' . $this->city . ', VA ' . $this->zip);
        } elseif (isset($this->address)) {
            $q = '?SingleLine=' . urlencode($this->address);
        } elseif (isset($this->zip)) {
            $q = '?ZIP=' . urlencode($this->zip);
        }

        # Assemble our URL, instructing the Virginia GIS server
        $url = 'https://vginmaps.vdem.virginia.gov/arcgis/rest/services/Geocoding/VGIN_Composite_Locator/GeocodeServer/findAddressCandidates'
            . $q . '&maxLocations=1&f=pjson';

        # Fetch the result
        $response = get_content($url);

        # If the response is no good.
        if ($response === false) {
            return false;
        }

        # Turn the JSON into a PHP array.
        $response = json_decode($response, true);

        if ($response == false) {
            return false;
        }

        # If there are no address candidates, bail.
        if (count($response['candidates']) == 0) {
            return false;
        }

        # In theory there could be multiple responses, but we don't have any method of dealing with
        # that outcome, so we simply return the first match and hope it's right. We save them to the
        # object namespace, too, since this function is generally followed by coords_to_districts().
        $this->latitude = $response['candidates'][0]['location']['y'];
        $this->longitude = $response['candidates'][0]['location']['x'];

        $coordinates = array();
        $coordinates['latitude'] = $this->latitude;
        $coordinates['longitude'] = $this->longitude;
        return $coordinates;
    }

    /**
     * Convert the instance's latitude and longitude into Virginia district identifiers.
     *
     * @return stdClass|false Object containing `house` and/or `senate` IDs, or false on failure.
     */
    public function coords_to_districts()
    {

        if (!isset($this->latitude) || !isset($this->longitude)) {
            return false;
        }

        # Assemble our URL.
        $url = 'https://v3.openstates.org/people.geo?apikey=' . OPENSTATES_KEY . '&lat='
                . $this->latitude . '&lng=' . $this->longitude;

        # Retrieve the resulting JSON..
        $district = get_content($url);

        # If we couldn't retrieve that content, then bail.
        if ($district === false) {
            return false;
        }

        $district = json_decode($district, true);

        $district = array_values(array_filter(
            isset($district['results']) ? $district['results'] : [],
            static function ($item) {
                return isset($item['jurisdiction']['classification'])
                    && $item['jurisdiction']['classification'] === 'state';
            }
        ));

        # If this isn't an array with two elements (one for each legislator), bail.
        if (count($district) != 2) {
            return false;
        }

        $result = new stdClass();
        foreach ($district as $legislator) {
            # If it's the house.
            if ($legislator['current_role']['org_classification'] == 'lower') {
                $result->house = district_to_id($legislator['current_role']['district'], 'house');
            }

            # Else if it's the senate.
            elseif ($legislator['current_role']['org_classification'] == 'upper') {
                $result->senate = district_to_id($legislator['current_role']['district'], 'senate');
            }
        }

        if (count((array)$result) == 0) {
            return false;
        }

        return $result;
    }
}
