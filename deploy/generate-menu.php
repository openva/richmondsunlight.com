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

    $legislators[$legislator{'chamber'}][substr($legislator{'name'}, 0, 1)][] = '<a href="/legislator/' . $legislator['shortname']
        . '/">' . $legislator['name_formatted'] . '</a>';

}

/*
 * Establish our alphabetical groupings
 */
$alphabet = explode(',', 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z');
$house_categories = explode(',', 'A,I,D,M,S');
$senate_categories = explode(',', 'A,J,S');

////////////////////////////////////////////////////////////////////////
///// Redo this to be based on iterating through the alphabet, NOT 
///// iterating through the list of legislators. Missing alphabetical
///// letters from legislators names is hobbling this.
////////////////////////////////////////////////////////////////////////

/*
 * Output menu data
 */
echo '
<ul>
    <li>House »
        <ul class="alphabetic">';

$first = true;
foreach ($legislators['house'] as $letter => $by_letter)
{

    if (in_array($letter, $house_categories) || $first == true)
    {
        echo 
            '<li>' . $letter . ' »
            <ul class="legislators">';
    }

    foreach ($by_letter as $legislator)
    {
        echo '
                <li>' . $legislator . '</li>';
    }

    if (in_array($alphabet[array_search($letter, $alphabet)]+1, $house_categories))
    {
        echo '
            </ul></li>';
    }

    $first = false;

}

echo '
    </li>
    <li>Senate »
        <ul class="alphabetic">';

$first = true;
foreach ($legislators['senate'] as $letter => $by_letter)
{

    if (in_array($letter, $senate_categories) || $first == true)
    {
        echo '
            <li>' . $letter . ' »
            <ul class="legislators">';
    }

    foreach ($by_letter as $legislator)
    {
        echo '
                <li>' . $legislator . '</li>';
    }

    if (in_array($alphabet[array_search($letter, $alphabet)]+1, $senate_categories))
    {
        echo '
            </ul></li>';
    }

    $first = false;

}

echo '
            </ul>
        </li>
        </ul>
    </li>
</ul>';
