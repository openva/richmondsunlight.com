<?php

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/settings.inc.php';
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
    <div id="portfolio-sidebar">
        <h1>Your Bill Portfolio</h1>
        <div id="portfolio-list">
            <div class="bill changed">
                <a href="/bill/hb1052/" class="balloon">HB1052</a>: Catchline that says a lot.
                <span class="last-updated">today</span>
            </div>
            <div class="bill changed">
                <a href="/bill/hb12/" class="balloon">HB12</a>: Catchline that says something else.
                <span class="last-updated">yesterday</span>
            </div>
            <div class="bill">
                <a href="/bill/sb670/" class="balloon">SB670</a>: Catchline that says something else entirely.
            </div>
            <div class="bill">
                <a href="/bill/sj6/" class="balloon">SJ6</a>: Catching that says something great about a person.
            </div>
        </div>
    <div>

    <style>
        #portfolio-sidebar {
            width: 250px;
            min-height: 300px;
            max-height: 600px;
            padding: 5px;
            right: 0px;
            z-index: 100;
            background-color: white;
            border: 5px solid black;
        }
            #portfolio-sidebar .bill {
                padding: 2px;
            }
                #portfolio-sidebar .bill+.bill {
                    margin-top: 1em;
                }
                #portfolio-sidebar .bill.changed {
                    background-color: yellow;
                }
                #portfolio-sidebar .bill .last-updated {
                    text-transform: uppercase;
                    font-size: .8em;
                    color: red;
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

        // See whether localstorage differs from the API.
        function check_for_updates() {



        }

        // Populate localstorage with the contents of this portfolio.
        function portfolio_store() {

            var store = {};

            // iterate through every "portfolios" element
            $.each(portfolios, function(index, portfolio_hash) {

                url = 'https://api.richmondsunlight.com/1.1/photosynthesis/' + portfolio_hash + '.json';
                $.getJSON(url, function(data) {
                    store[portfolio_hash] = data;
                });
            });

            store.updated = + new Date();

            console.log(store);
            console.log(JSON.stringify(store));

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
