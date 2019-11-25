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
        <h1><a class="handle">Your Bill Portfolio</a></h1>
        <div id="portfolio-list"></div>
    <div>

    <!-- slider -->
    <link rel="stylesheet" href="https://cdn.rawgit.com/hawk-ip/jquery.tabSlideOut.js/v2.4/jquery.tabSlideOut.css"> 
    <script src="https://use.fontawesome.com/2be9406092.js"></script>
    <script src="https://cdn.rawgit.com/hawk-ip/jquery.tabSlideOut.js/v2.4/jquery.tabSlideOut.js"></script>
    <script>
        $('#portfolio-sidebar').tabSlideOut( {'tabLocation':'bottom'} );
    </script>

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

    <script>
        var portfolios_bills = [];

        // Create a list of all bills in all portfolios
        $.each( portfolios, function( index, portfolio_hash ) {
            url = 'https://api.richmondsunlight.com/1.1/photosynthesis/' + portfolio_hash + '.json';
            $.getJSON(url, function(data) {
                $.each( data.bills, function( index, bill ) {
                    portfolios_bills.push(bill);
                    $( "#portfolio-list" ).append( "<div class=bill><a href=" + bill.url + ">"
                        + bill.number + "</a>: " + bill.catch_line + "</div>");
                });
            });
        });

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
