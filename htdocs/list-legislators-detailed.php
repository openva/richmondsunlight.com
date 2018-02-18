<?php

###
# Detailed Legislators Listing Page
#
# PURPOSE
# Lists all current representatives, but with lots of details about them.
#
# NOTES
# None.
#
# TODO
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'includes/functions.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# PAGE METADATA
$page_title = 'Detailed Legislator Listing';
$site_section = 'legislators';

# Include the tabbing code.
$html_head = '<script src="/js/sorttable.js" type="text/javascript"></script>';

# PAGE CONTENT

# Import the variables.
if (isset($_GET['options']) && !empty($_GET['options']))
{
    $options = $_GET['options'];
}

# Set some default options if the page is just being loaded or if no options have been chosen.
if ((!isset($options)) || (isset($options) && (count($options) == 0)))
{
    $options = array('party' => 'y', 'chamber' => 'y');
}

# Select all active legislators from the database.
$sql = 'SELECT representatives.shortname, representatives.name, representatives.party,
		districts.number AS district, representatives.chamber,
		representatives.place AS location,
		ROUND( DATEDIFF(now(),representatives.birthday) / 365 ) AS age,
		DATE_FORMAT(representatives.date_started, "%Y") AS year_started,
		representatives.partisanship, representatives.sex, representatives.race
		FROM representatives
		LEFT JOIN districts
		ON representatives.district_id = districts.id
		WHERE (representatives.date_ended IS NULL
			OR representatives.date_ended > now())
		ORDER BY representatives.chamber ASC, representatives.name ASC';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{

    # This is the code we'll use to specify that a checkbox should be checked by default.
    $checked = ' checked="checked"';

    $page_body .= '
		<p>Check any attribute that you want to include. Click on the title of any column to
		sort by that attribute.</p>

		<form method="get" action="/legislators/detailed/">

			<!--<input type="checkbox" name="options[age]" value="y" id="age" '
            .(isset($options['age']) ? $checked : '').
            '/>
			<label for="age">Age</label>-->

			<input type="checkbox" name="options[location]" value="y" id="location" '
            .(isset($options['location']) ? $checked : '').
            '/>
			<label for="location">Location</label>

			<input type="checkbox" name="options[chamber]" value="y" id="chamber" '
            .(isset($options['chamber']) ? $checked : '').
            '/>
			<label for="chamber">Chamber</label>

			<input type="checkbox" name="options[party]" value="y" id="party" '
            .(isset($options['party']) ? $checked : '').
            '/>
			<label for="party">Party</label>

			<!--<input type="checkbox" name="options[cash]" value="y" id="cash" '
            .(isset($options['cash']) ? $checked : '').
            '/>
			<label for="cash">$ on Hand</label>-->

			<input type="checkbox" name="options[partisanship]" value="y" id="partisanship" '
            .(isset($options['partisanship']) ? $checked : '').
            '/>
			<label for="partisanship">Partisanship</label>

			<input type="checkbox" name="options[sex]" value="y" id="sex" '
            .(isset($options['sex']) ? $checked : '').
            '/>
			<label for="sex">Sex</label>

			<input type="checkbox" name="options[race]" value="y" id="race" '
            .(isset($options['race']) ? $checked : '').
            '/>
			<label for="race">Race</label>

			<input type="checkbox" name="options[year_started]" value="y" id="year_started" '
            .(isset($options['year_started']) ? $checked : '').
            '/>
			<label for="year_started">Started</label>

			<input type="submit" name="go" value="Go" />

		</form>

		<table id="legislators" class="sortable">
		<thead>
			<tr>
				<th>Name</th>';
        if (isset($options['age']))
        {
            $page_body .= '
				<th>Age</th>';
        }
        if (isset($options['location']))
        {
            $page_body .= '
				<th>Location</th>';
        }
        if (isset($options['chamber']))
        {
            $page_body .= '
				<th>Chamber</th>';
        }
        if (isset($options['party']))
        {
            $page_body .= '
				<th>Party</th>';
        }
        if (isset($options['cash']))
        {
            $page_body .= '
				<th>$ on Hand</th>';
        }
        if (isset($options['partisanship']))
        {
            $page_body .= '
				<th>Partisanship</th>';
        }
        if (isset($options['sex']))
        {
            $page_body .= '
				<th>Sex</th>';
        }
        if (isset($options['race']))
        {
            $page_body .= '
				<th>Race</th>';
        }
        if (isset($options['year_started']))
        {
            $page_body .= '
				<th>Started</th>';
        }
    $page_body .= '
			</tr>
		</thead>
		<tbody>';
    while ($legislator = mysql_fetch_array($result))
    {
        $legislator = array_map('stripslashes', $legislator);

        if (!empty($legislator['cash']))
        {
            $legislator['cash'] = '$'.number_format($legislator['cash']);
        }

        $page_body .= '
			<tr>
				<td><a href="/legislator/'.$legislator['shortname'].'/">'
                    .pivot($legislator['name']).'</a></td>';

        if (isset($options['age']))
        {
            $page_body .= '
				<td>'.$legislator['age'].'</td>';
        }
        if (isset($options['location']))
        {
            $page_body .= '
				<td>'.$legislator['location'].'</td>';
        }
        if (isset($options['chamber']))
        {
            $page_body .= '
				<td>'.$legislator['chamber'].'</td>';
        }
        if (isset($options['party']))
        {
            $page_body .= '
				<td>'.$legislator['party'].'</td>';
        }
        if (isset($options['cash']))
        {
            $page_body .= '
				<td>'.$legislator['cash'].'</td>';
        }
        if (isset($options['partisanship']))
        {
            if (!empty($legislator['partisanship']))
            {
                $page_body .= '
					<td sorttable_customkey="'.$legislator['partisanship'].'">
						<div id="partisanship-graph" style="height: 10px;">
							<div style="height: 12px; width: '.$legislator['partisanship'].'%;"></div>
						</div>
					</td>';
            }
            else
            {
                $page_body .= '
					<td></td>';
            }
        }
        if (isset($options['sex']))
        {
            $page_body .= '
				<td>'.$legislator['sex'].'</td>';
        }
        if (isset($options['race']))
        {
            $page_body .= '
				<td>'.$legislator['race'].'</td>';
        }
        if (isset($options['year_started']))
        {
            $page_body .= '
				<td>'.$legislator['year_started'].'</td>';
        }
        $page_body .= '
			</tr>';
    }
    $page_body .= '
		</table>';
}

# OUTPUT THE PAGE
/*display_page('page_title='.urlencode($page_title).'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
    '&site_section='.urlencode($site_section).'&body_tag='.urlencode($body_tag).'&html_head='.urlencode($html_head));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->body_tag = $body_tag;
$page->html_head = $html_head;
$page->process();
