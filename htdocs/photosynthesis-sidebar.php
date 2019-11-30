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
        <h5><a href="/photosynthesis/" class="handle">Your Bill Portfolio</a></h5>
        <div id="portfolio-list"></div>
    </div>

    <!-- slider -->
    <script src="/js/vendor/jquery.tabSlideOut.js/jquery.tabSlideOut.js"></script>
    <link rel="stylesheet" href="/js/vendor/jquery.tabSlideOut.js/jquery.tabSlideOut.css"> 

    <script>
        
        $('#portfolio-sidebar').tabSlideOut({'tabLocation':'right','action':'click'});

        // List all bills in all portfolios
        $.each( portfolios, function( index, portfolio_hash ) {
            url = 'https://api.richmondsunlight.com/1.1/photosynthesis/' + portfolio_hash + '.json';
            console.log(url);
            $.getJSON(url, function(data) {
                $.each( data.bills, function( index, bill ) {
                    $( "#portfolio-list" ).append( '<div class="bill"><a href="' + bill.url + '">'
                        + bill.number + '</a>: ' + bill.catch_line + '</div>');
                });
            });
        });

    </script>

    <style>
        #portfolio-sidebar {
            width: 250px;
            min-height: 300px;
            max-height: 400px;
            padding: 5px;
            z-index: 100;
            background-color: #f4eee5;
            border: 1px solid #790806;
        }
            #portfolio-sidebar h5
            {
                font-size: 1em;
                font-weight: normal;
                padding-bottom: 0;
            }
                #portfolio-sidebar h5 a.handle {
                    font-family: Verdana, Lucida Grande, sans serif;
                    background-color: #790806;
                    color: white;
                    border-radius: 10px 10px 0 0;
                    text-decoration: none;
                    font-weight: normal;
                }
                #portfolio-sidebar h5 a.handle:hover {
                    text-decoration: none;
                }
                #portfolio-list {
                    max-height: 385px;
                    overflow: auto;
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
