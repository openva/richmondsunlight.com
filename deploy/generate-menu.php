<?php

/**
 * Generate Legislators Menu
 * 
 * Query the database to generate a list of all legislators, to update the menu.
 * The resulting list is sent to stdout.
 **/

$database = new Database;
$db = $database->connect_mysqli();

/*
 * Get a list of all legislators.
 */
$sql = 'SELECT name_formatted, shortname, chamber
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
    $legislators[$legislator{'chamber'}][] = '<li><a href="/legislator/' . $legislator['shortname']
        . ' /">' . $legislator . '</a></li>';

}

/*
 *
 */
echo '
<ul>
    <li>House »
        <ul class="alphabetic">
        <li>A–Z »
            <ul class="legislators">
                ' . implode("\t", $legislators['house']) . '
            </ul>
        </li>
        </ul>
    </li>
    <li>Senate »
        <ul class="alphabetic">
        <li>A–Z »
            <ul class="legislators">
                ' . implode("\t", $legislators['senate']) . '
            </ul>
        </li>
        </ul>
    </li>
</ul>';
