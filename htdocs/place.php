<?php

/**
 * Place Listing Page 
 * 
 * Lists all places that we have records of legislation for
 */

/*
 * INCLUDES
 * Include any files or libraries that are necessary for this specific page to function.
 */
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

/*
 * DECLARATIVE FUNCTIONS
 * Run those functions that are necessary prior to loading this specific page.
 */
$database = new Database;
$database->connect();

/*
 * Initialize the sessio
 */
session_start();

/*
 * Localize variables
 */
if (!empty($_GET['place']))
{
    $place = filter_input(INPUT_GET, 'place', FILTER_SANITIZE_SPECIAL_CHARS);
    $place = urldecode($place);
}
if (!empty($_GET['year']))
{
    $year = filter_input(INPUT_GET, 'year', FILTER_SANITIZE_NUMBER_INT);
    if ($year < 2000 || $year > 2040)
    {
        unset($year);
    }
}
else
{
    $year = SESSION_YEAR;
}

/*
 * Page metadata
 */
if (!empty($place))
{
    $page_title = SESSION_YEAR . ' Bills Affecting ' . $place;
}
else
{
    $page_title = $year . ' Bills Affecting ' . $place;
}
$site_section = 'bills';

/*
 * PAGE CONTENT
 */

$places = new Places;

/*
 * If we're looking at a specific place.
 */
if (!empty($place))
{

    $bills = $places->bills($place);
    if ($bills == false)
    {
        $page_body .= '<p>No bills found in ' . $place . ' in ' . $year . '</p>';
    }
    else
    {

        $num_results = count($bills);
        $page_body .= '<p>' . number_format($num_results) . ' bill'
            . ($num_results > 1 ? 's' : '') . ' found.</p>';

        
        $page_body .= '
        <table id="' . urlencode($place) . '" class="bill-listing sortable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($bills as $bill)
        {

            /*
             * Simplify the status text.
             */
            if (mb_stristr($bill['status'], 'failed') !== FALSE)
            {
                $bill['status'] = 'dead';
            }

            /*
             * We want to display the house bills, then the senate bills. But we need some way to
             * know when we've crossed that boundary, and that's what we use the $chamber flag for.
             */
            if (!isset($chamber))
            {
                $chamber = $bill['chamber'];
                $page_body .= '
                <div class="tabs">
                <ul>
                    <li><a href="#house">House</a></li>
                    <li><a href="#senate">Senate</a></li>
                </ul>
                <div id="' . $chamber . '">
                    <table id="' . $chamber . '" class="bill-listing sortable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
            }
            elseif ($chamber != $bill['chamber'])
            {
                $chamber = $bill['chamber'];
                $page_body .= '</tbody>
                    </table>
                </div>
                <div id="' . $chamber . '">
                    <table id="' . $chamber . '" class="bill-listing sortable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
            }
            $page_body .= '<tr>
                            <td><a href="/bill/' . $bill['year'] . '/' . $bill['number'] .
                                '/" class="balloon">' . mb_strtoupper($bill['number']) .
                                balloon($bill, 'bill') . '</a></td>
                            <td>' . $bill['catch_line'] . '</td>
                            <td>' . $bill['status'] . '</td>
                        </tr>';
        }
        
        $page_body .= '</tbody></table></div></div>';

    }

}

/*
 * If we're just loading the page fresh and need a list of all places.
 */
else
{

    $place_list = $places->list();
    foreach ($place_list as $place)
    {
        $page_body .= '<li><a href="/place/'. urlencode($place['name']) . '/">' . $place['name']
            . '</a></li>';
    }

}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();