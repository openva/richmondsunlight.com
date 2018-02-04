<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/settings.inc.php';
include_once 'includes/functions.inc.php';
include_once 'vendor/autoload.php';

session_start();

if (isset($_SESSION['portfolios']))
{

    // Make our portfolio IDs available to JavaScript.
    $page_body = '<script>var portfolios = [];';
    foreach ($_SESSION['portfolios'] as $portfolio)
    {
        $page_body .= 'portfolios.push("' . $portfolio['hash'] . '");';
    }
    $page_body .= '</script>';

    $page_body .= <<<EOD
    <div id="portfolio-sidebar" style="display: none;">
        <h1>Your Bill Portfolio</h1>
        <div id="portfolio-list">

        </div>
    <div>

    <style>
        #portfolio-sidebar {
            width: 250px;
            height: 600px;
            right: 0px;
            z-index: 100;
            background-color: white;
            border: 5px solid black;
        }

    </style>

    <script src="https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js"></script>
    <script>
        /* MAKE SURE THIS WORKS FOR THE MAIN PORTFOLIO PAGE, TOO */

        // query localstorage
        //var rawPortfolio = localStorage.getItem('portfolio');
        //var portfolio = JSON.parse(rawPortfolio);
            // if nothing is in localstorage, run the function to populate it
            // when did they most recently view their portfolio?
            // what is the most recent update of a bill in the portfolio?
            // if most recent update is since the most recent view
                // change color of tab

        // iterate through bills
            // append to portfolio element
            // if bill has been changed since localstorage time, class="changed"

        // function to populate localstorage
        function portfolio_store() {

            var store = {};

            // iterate through every "portfolios" element
            $.each(portfolios, function(index, portfolio_hash) {

                url = 'https://api.richmondsunlight.com/1.1/photosynthesis/' + portfolio_hash + '.json';
                $.getJSON(url, function(data) {
                    store[portfolio_hash] = data;
                });
            });

            console.log(store);

            // store built-up object in localstorage
            localStorage.setItem('portfolio', JSON.stringify(store));

        }

        portfolio_store();

    </script>
EOD;
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
