<?php

/**
 * Generate Legislators Menu
 * 
 * Query the database to generate a list of all legislators, to update the menu.
 * The resulting list is sent to stdout.
 **/

require '../htdocs/includes/settings.inc.php';
require '../htdocs/includes/class.Database.php';

$database = new Database;
$db = $database->connect_mysqli();

/*
 * Get a list of all legislators.
 */
$sql = 'SELECT name, name_formatted, shortname, chamber
        FROM representatives
        WHERE date_ended IS NOT NULL OR date_ended >= now()
        ORDER BY chamber ASC, name ASC';
$result = mysqli_query($db, $sql);

$legislators = array('house' => array(), 'senate' => array());

/*
 * Build up an HTML-formatted array of legislators by chamber.
 */
while ($legislator = mysqli_fetch_assoc($result))
{

    $legislator = array_map('stripslashes', $legislator);
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
