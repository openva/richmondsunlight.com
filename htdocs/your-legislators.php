<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# PAGE METADATA
$page_title = 'Your Legislators';
$site_section = 'legislators';

# PAGE CONTENT

# If the form is being submitted.
if (!empty($_GET['street']) && !empty($_GET['city']) && !empty($_GET['zip']))
{
    $location = new Location;
    $location->street = $_GET['street'];
    $location->city = $_GET['city'];
    $location->zip = $_GET['zip'];
    $coordinates = $location->get_coordinates();

    if ($coordinates != FALSE)
    {
        $districts = $location->coords_to_districts();

        if ($districts != FALSE)
        {
            $sql = 'SELECT representatives.shortname, representatives.name_formatted AS name,
					districts.number, districts.id AS district_id, representatives.chamber
					FROM representatives
					LEFT JOIN districts
						ON representatives.district_id=districts.id
					WHERE representatives.district_id=' . current($districts) . '
                        OR representatives.district_id=' . next($districts);
            $result = mysql_query($sql);
            if (mysql_num_rows($result) == 0)
            {
                $page_body .= '<p>Your legislators could not be identified.</p>';
            }
            else
            {
                $page_body .= '
					<p>Your two legislators have been identified. They are:</p>
					<ul>';
                while ($legislator = mysql_fetch_assoc($result))
                {
                    $legislator = array_map('stripslashes', $legislator);
                    $page_body .= '<li><a href="/legislator/' . $legislator['shortname'] . '/">'
                        . $legislator['name'] . '</a></li>';

                    # Save this for updating the user's account.
                    if ($legislator['chamber'] == 'house')
                    {
                        $house_district_id = $legislator['district_id'];
                    }
                    elseif ($legislator['chamber'] == 'senate')
                    {
                        $senate_district_id = $legislator['district_id'];
                    }
                }
                $page_body .= '</ul>';

                # If this is a registered user, update his record to store his location and
                # districts.
                if (logged_in() === TRUE)
                {
                    update_user('zip=' . $_GET['zip'] .
                        '&city=' . $_GET['city'] .
                        '&latitude=' . $coordinates['latitude'] .
                        '&longitude=' . $coordinates['longitude'] .
                        '&house_district_id=' . $house_district_id .
                        '&senate_district_id=' . $senate_district_id);
                }
            }
        }
        else
        {
            $page_body .= '<p>Your address could be located, but we do not have a record of a Virginia
				legislator for that location. So sorry!</p>';
        }
    }
    else
    {
        $page_body .= '<p>Your address could not be identified as a real place. Are you sure you entered it
			correctly?</p>';
    }
}
else
{
    $page_body = '
		<p>“Who’s my legislator?", you may be wondering. Enter your address to find out who
		represents you in the Virginia House of Delegates and the Virginia Senate.</p>
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
		</style>
		<form method="get" action="/your-legislators/" class="address">
			<fieldset>

				<label for="form-street">Street Address</label>
				<input type="text" size="39" name="street" id="form-street" placeholder="Street Address" /><br />

				<label for="form-city">City</label>
				<input type="text" size="30" maxlength="30" name="city" id="form-city" placeholder="City" />

				<label for="form-zip">ZIP</label>
				<input type="text" size="5" maxlength="5" name="zip" id="form-zip" placeholder="ZIP" /><br />

				<input type="submit" value="Submit" />
			</fieldset>
		</form>
	';
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
