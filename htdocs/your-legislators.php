<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific page.
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# PAGE METADATA
$page_title = 'Your Legislators';
$site_section = 'legislators';

# PAGE CONTENT

$page_body = '';

# Determine whether we have enough information to perform a lookup.
$has_address = !empty($_GET['street']) && !empty($_GET['city']) && !empty($_GET['zip']);
$has_coordinates = isset($_GET['latitude'], $_GET['longitude'])
    && $_GET['latitude'] !== '' && $_GET['longitude'] !== '';

# If the form is being submitted with an address or coordinates.
if ($has_address || $has_coordinates) {
    $location = new Location();
    $coordinates = false;

    if ($has_address) {
        $location->street = $_GET['street'];
        $location->city = $_GET['city'];
        $location->zip = $_GET['zip'];
    }

    if ($has_coordinates && is_numeric($_GET['latitude']) && is_numeric($_GET['longitude'])) {
        $location->latitude = (float)$_GET['latitude'];
        $location->longitude = (float)$_GET['longitude'];
        $coordinates = array(
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        );
    } elseif ($has_address) {
        $coordinates = $location->get_coordinates();
    }

    if ($coordinates != false) {
        $districts = $location->coords_to_districts();

        if ($districts != false) {
            $district_ids = array();
            if (isset($districts->house)) {
                $house_district_id = (int)$districts->house;
                $district_ids[] = $house_district_id;
            }
            if (isset($districts->senate)) {
                $senate_district_id = (int)$districts->senate;
                $district_ids[] = $senate_district_id;
            }

            if (empty($district_ids)) {
                $page_body .= '<p>Your legislators could not be identified.</p>';
            } else {
                $sql = 'SELECT representatives.shortname, representatives.name_formatted AS name,
					districts.number, districts.id AS district_id, representatives.chamber
					FROM representatives
					LEFT JOIN districts
						ON representatives.district_id=districts.id
					WHERE representatives.district_id IN (' . implode(',', $district_ids) . ')';
                $result = mysqli_query($GLOBALS['db'], $sql);
                if (mysqli_num_rows($result) == 0) {
                    $page_body .= '<p>Your legislators could not be identified.</p>';
                } else {
                    $page_body .= '
					<p>Your two legislators have been identified. They are:</p>
					<ul>';
                    while ($legislator = mysqli_fetch_assoc($result)) {
                        $legislator = array_map('stripslashes', $legislator);
                        $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">'
                            . $legislator['name'] . '</a></li>';

                        # Save this for updating the user's account.
                        if ($legislator['chamber'] == 'house') {
                            $house_district_id = $legislator['district_id'];
                        } elseif ($legislator['chamber'] == 'senate') {
                            $senate_district_id = $legislator['district_id'];
                        }
                    }
                    $page_body .= '</ul>';

                    # If this is a registered user, update his record to store his location and
                    # districts.
                    if (
                        logged_in() === true
                        && !empty($_GET['zip'])
                        && !empty($_GET['city'])
                    ) {
                        update_user('zip=' . $_GET['zip'] .
                            '&city=' . $_GET['city'] .
                            '&latitude=' . $coordinates['latitude'] .
                            '&longitude=' . $coordinates['longitude'] .
                            '&house_district_id=' . $house_district_id .
                            '&senate_district_id=' . $senate_district_id);
                    }
                }
            }
        } else {
            $page_body .= '<p>Your location could be identified, but we do not have a record of a Virginia
				legislator for that location. So sorry!</p>';
        }
    } else {
        $page_body .= '<p>Your location could not be identified. If you entered an address, please make sure it
			is correct, or try again with your browser\'s location.</p>';
    }
} else {
    $page_body = '
		<p>“Who’s my legislator?", you may be wondering. Enter your address or let your browser share your location to find out who represents you in the Virginia House of Delegates and the Virginia Senate.</p>
		<style>
			form.address label {
				display: none;
			}
			form.address fieldset {
				width: 300px;
				margin: 2em auto;
			}
			form input#form-street {
				width: 100%;
			}
			form input#form-city {
				clear: left;
				width: 65%;
			}
			form input#form-zip {
				float: right;
				width: 25%;
			}
			form input[type="submit"] {
				float: right;
			}
			form button#form-use-location {
				float: left;
				margin-top: 0.5em;
			}
			form p#geolocation-error {
				clear: both;
				color: #a00;
				display: none;
				margin-top: 1em;
			}
		</style>
		<form method="get" action="/your-legislators/" class="address">
			<fieldset>

				<label for="form-street">Street Address</label>
				<input type="text" size="39" name="street" id="form-street" placeholder="Street Address" /><br />

				<label for="form-city">City</label>
				<input type="text" size="30" maxlength="30" name="city" id="form-city" placeholder="City" />

				<label for="form-zip">ZIP</label>
				<input type="text" size="5" maxlength="5" name="zip" id="form-zip" placeholder="ZIP" /><br />

				<input type="hidden" name="latitude" id="form-latitude" />
				<input type="hidden" name="longitude" id="form-longitude" />

				<button type="button" id="form-use-location">Use My Location</button>
				<input type="submit" value="Submit" />

				<p id="geolocation-error"></p>
			</fieldset>
		</form>
		<script>
			(function () {
				var button = document.getElementById(\'form-use-location\');
				if (!button) {
					return;
				}

				if (!navigator.geolocation) {
					button.style.display = \'none\';
					return;
				}

				var latitudeInput = document.getElementById(\'form-latitude\');
				var longitudeInput = document.getElementById(\'form-longitude\');
				var addressForm = document.querySelector(\'form.address\');
				var errorMessage = document.getElementById(\'geolocation-error\');

				button.addEventListener(\'click\', function () {
					if (errorMessage) {
						errorMessage.style.display = \'none\';
						errorMessage.textContent = \'\';
					}
					button.disabled = true;
					button.textContent = \'Locating...\';

					navigator.geolocation.getCurrentPosition(function (position) {
						latitudeInput.value = position.coords.latitude;
						longitudeInput.value = position.coords.longitude;

						button.disabled = false;
						button.textContent = \'Use My Location\';

						if (
							!document.getElementById(\'form-street\').value
							&& !document.getElementById(\'form-city\').value
							&& !document.getElementById(\'form-zip\').value
						) {
							addressForm.submit();
						}
					}, function (error) {
						button.disabled = false;
						button.textContent = \'Use My Location\';
						if (errorMessage) {
							errorMessage.style.display = \'block\';
							errorMessage.textContent = \'Unable to retrieve your location: \' + error.message;
						}
					}, {
						enableHighAccuracy: true
					});
				});
			})();
		</script>
	';
}

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
