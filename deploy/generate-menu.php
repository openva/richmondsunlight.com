<?php

/**
 * Generate Legislators Menu
 * 
 * Query the database to generate a list of all legislators, to update the menu.
 * The resulting list is sent to stdout.
 **/

require '../htdocs/includes/settings.inc.php';
require '../htdocs/includes/class.Database.php';
require '../htdocs/includes/vendor/autoload.php';

$database = new Database;
$db = $database->connect_mysqli();

/*
 * Get a list of all legislators.
 */
$legislator = new Legislator;
$legislator_list = $legislator->get_list('current');

$legislators = array('house' => array(), 'senate' => array());

/*
 * Build up an HTML-formatted array of legislators by chamber.
 */
foreach ($legislator_list as $legislator)
{

    $legislators[$legislator{'chamber'}][substr($legislator{'name'}, 0, 1)][] = '<li><a href="/legislator/' . $legislator['shortname']
        . '/">' . $legislator['name_formatted'] . '</a></li>';

}

/*
 * Establish our alphabetical groupings
 */
$house_categories = explode(',', 'a,i,d,m,s');
$senate_categories = explode(',',  'a,n');

/*
 * Output menu data
 */
echo '
<ul>
    <li>House »
        <ul class="alphabetic">
        <li>A–Z »
            <ul class="legislators">';

foreach ($legislators['house'] as $letter => $by_letter)
{
    echo '<li>' . $letter . ' »
        <ul class="legislators">';
    foreach ($by_letter as $legislator)
    {
        echo '<li>' . $legislator . '</li>';
    }
    echo '</ul></li>';
}

echo '
    </li>
    <li>Senate »
        <ul class="alphabetic">';

            foreach ($legislators['senate'] as $letter => $by_letter)
            {
                echo '<li>' . $letter . ' »
                    <ul class="legislators">';
                foreach ($by_letter as $legislator)
                {
                    echo '<li>' . $legislator . '</li>';
                }
                echo '</ul></li>';
            }

echo '
            </ul>
        </li>
        </ul>
    </li>
</ul>';
