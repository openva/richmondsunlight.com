<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta charset="utf-8">
<meta name=viewport content="width=device-width, initial-scale=1">
<title>%browser_title%</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#790806">
<link rel="stylesheet" href="/css/new/reset.css?v=%css_version%" media="screen">
<link rel="stylesheet" href="/css/new/master.css?v=%css_version%" media="screen">
<link rel="stylesheet" href="/css/new/menu.css?v=%css_version%" media="screen">
<link rel="stylesheet" href="/css/new/print.css?v=%css_version%" media="print">
<link rel="stylesheet" href="/css/page-elements.css?v=%css_version%" media="screen">
<link rel="stylesheet" href="/css/jquery-ui.theme.min.css" media="screen">
<link rel="stylesheet" href="/js/vendor/qtip2/dist/jquery.qtip.min.css" media="screen">
<link rel="stylesheet" href="/js/vendor/jquery.tabSlideOut.js/jquery.tabSlideOut.css"> 
    
<script src="/js/vendor/jquery/dist/jquery.min.js"></script>
<script src="/js/vendor/jquery-ui/jquery-ui.min.js"></script>
<script src="/js/functions.js"></script>
<script src="/js/vendor/qtip2/dist/jquery.qtip.min.js"></script>
<script src="/js/vendor/jquery.tabSlideOut.js/jquery.tabSlideOut.js"></script>

<!-- For IE 11, Chrome, Firefox, Safari, Opera -->
<link rel="icon" type="image/png" href="/images/favicons/16.png" sizes="16x16">
<link rel="icon" type="image/png" href="/images/favicons/32.png" sizes="32x32">
<link rel="icon" type="image/png" href="/images/favicons/48.png" sizes="48x48">
<link rel="icon" type="image/png" href="/images/favicons/62.png" sizes="62x62">
<link rel="icon" type="image/png" href="/images/favicons/192.png" sizes="192x192">

<!-- Add to Home Screen -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-status-bar-style" content="black">
<meta name="mobile-web-app-title" content="Richmond Sunlight">

<!-- iOS Touch Icons -->
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/76.png" sizes="76x76">
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/120.png" sizes="120x120">
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/152.png" sizes="152x152">
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/180.png" sizes="180x180">

<!-- Safari Pinned Site -->
<link rel="mask-icon" href="/images/favicons/safari_icon.svg">


%html_head%
</head>
<body%body_tag%>
    <div id="page-wrap">
        <div id="page">
            <header>
            <div id="header">
                <div id="logo">
                    <a href="/"><img src="/images/templates/new/richmond-sunlight-logo.png"
                        alt="Richmond Sunlight Logo" title="Richmond Sunlight" width="274" height="88"></a>
                </div>

                <div id="from-and-search">
                    <div id="recommendations">
                        %recommended_bills%
                    </div>
                    <form id="search" method="get" action="/search/">
                        <label for="search-box"><img src="/images/templates/new/search-label.gif"
                            width="56" height="20" alt="Search"></label>
                        <input type="search" name="q" id="search-box" placeholder="Search bills">
                        <input type="image" src="/images/templates/new/go-search.gif" alt="Go">
                    </form>
                </div>

                <div id="date-status">
                    <div id="date"></div>
                    <div id="status">
                        The General Assembly is not in session.
                    </div>
                    <div id="account">%account%</div>
                </div>

                <nav>
                <button type="button" id="nav-toggle" aria-controls="nav" aria-expanded="false" hidden>Menu</button>
                <ul id="nav">
                    <li id="t-home"><a href="/" accesskey="h">Home</a></li>
                    <li id="t-bills"><a href="/bills/" accesskey="b">Bills</a>
                        <ul>
                            <li class="nav-section-link"><a href="/bills/">All Bills</a></li>
                            <li><a href="/bills/topic/">By Topic</a></li>
                            <li><a href="/bills/introduced/">Newest</a></li>
                            <li><a href="/bills/activity/">Activity</a></li>
                            <li><a href="/bills/#house">House</a></li>
                            <li><a href="/bills/#senate">Senate</a></li>
                            <li class="past-years"><span class="nav-label" role="button" tabindex="0">Past Years »</span>
                                <ul>
                                    <li><a href="/bills/2006/">2006</a></li>
                                    <li><a href="/bills/2007/">2007</a></li>
                                    <li><a href="/bills/2008/">2008</a></li>
                                    <li><a href="/bills/2009/">2009</a></li>
                                    <li><a href="/bills/2010/">2010</a></li>
                                    <li><a href="/bills/2011/">2011</a></li>
                                    <li><a href="/bills/2012/">2012</a></li>
                                    <li><a href="/bills/2013/">2013</a></li>
                                    <li><a href="/bills/2014/">2014</a></li>
                                    <li><a href="/bills/2015/">2015</a></li>
                                    <li><a href="/bills/2016/">2016</a></li>
                                    <li><a href="/bills/2017/">2017</a></li>
                                    <li><a href="/bills/2018/">2018</a></li>
                                    <li><a href="/bills/2019/">2019</a></li>
                                    <li><a href="/bills/2020/">2020</a></li>
                                    <li><a href="/bills/2021/">2021</a></li>
                                    <li><a href="/bills/2022/">2022</a></li>
                                    <li><a href="/bills/2023/">2023</a></li>
                                    <li><a href="/bills/2024/">2024</a></li>
                                    <li><a href="/bills/2025/">2025</a></li>
                                    <li><a href="/bills/2026/">2026</a></li>
                                    <li><a href="/bills/2027/">2027</a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    
                    <li id="t-legislators"><a href="/legislators/" accesskey="l">Legislators</a>
                        <?php require('legislators.html'); ?>
                        <li class="nav-section-link"><a href="/legislators/">All Legislators</a></li>
                        <li><a href="/legislators/detailed/">Detailed Listing</a></li>
                        <li><a href="/committees/">Committees</a></li>
                        <li><a href="/your-legislators/">Your Legislators</a></li>
                        </ul>
                    </li>
                    <li id="t-places"><a href="/places/" accesskey="w">Places</a></li>
                    <li id="t-tools"><span class="nav-label" role="button" tabindex="0">Tools</span>
                        <ul>
                        <li><a href="/photosynthesis/" accesskey="p">Photosynthesis</a></li>
                        <li><a href="/photosynthesis/portfolios/">Public Portfolios</a></li>
                        <li><a href="https://api.richmondsunlight.com/docs/">API</a></li>
                        <li><a href="/downloads/">Bulk Downloads</a></li>
                        </ul>
                    </li>
                    <li id="t-video"><a href="/minutes/" accesskey="v">Video</a>
                        <ul>
                        <li class="nav-section-link"><a href="/minutes/">All Video</a></li>
                        <li><a href="/minutes/#house">House</a></li>
                        <li><a href="/minutes/#senate">Senate</a></li>
                        </ul>
                    </li>
                    <li id="t-schedule"><a href="/schedule/" accesskey="s">Schedule</a></li>
                    <li id="t-stats"><a href="/statistics/" accesskey="t">Stats</a></li>
                    <li id="t-about"><a href="/about/" accesskey="a">About</a>
                    <ul>
                        <li><a href="/about/">The General Assembly »</a>
                            <ul>
                            <li><a href="/about/#house">House</a></li>
                            <li><a href="/about/#senate">Senate</a></li>
                            </ul>
                        </li>
                        <li><a href="/about/site/">Richmond Sunlight</a></li>
                    </ul>
                    </li>
                </ul>
                </nav>
            </div>
            </header>

            <main>

            <div id="content">
                <h1>%page_title%</h1>
                %page_body%
            </div>

            <div id="portfolio-sidebar" style="display: none">
                <h5><a href="/photosynthesis/" class="handle">Your Bill Portfolio</a></h5>
                <div id="portfolio-list"></div>
            </div>

            </main>

            <aside>
            <div id="sidebar">
                %page_sidebar%
            </div>
            </aside>

            <footer>
            <div id="footer">
                <p><a href="/about/site/">About the Site</a> | <a href="/about/rss/">RSS
                Subscriptions</a> | <a href="/about/api/">API</a> | <a href="/downloads/">Bulk Downloads</a>
                | <a href="/contact/">Contact Richmond Sunlight</a> | <a href="/about/tos/">Terms of
                Service</a></p>

                <p>A program of <a href="http://www.openva.com/">Open Virginia</a>. Created by
                <a href="https://waldo.jaquith.org/">Waldo Jaquith</a>. Design by <a
                href="http://www.meticulous.com/">Meticulous Design Group</a>. All data is released
                under a <a href="https://creativecommons.org/publicdomain/zero/1.0/">Creative
                Commons Zero license</a>. All creative content published under a
                <a href="https://creativecommons.org/licenses/by-sa/3.0/us/">CC BY-SA 3.0 US</a>
                license. <a href="https://github.com/openva/richmondsunlight.com">All source code is
                available on GitHub</a> — pull requests welcome! — published under the MIT License.
                <a href="https://updown.io/vyvj">See our system status dashboard</a>.</p>

                <p
                class="quote">“Sunlight is said to be the best of disinfectants.” —Justice Louis
                Brandeis, 1914</p>

            </div>
            </footer>
        </div>
    </div>

    <script>
        /* The menu on phones (see menu.css): the Menu button opens it, and tapping a section
           opens that section's submenu, rather than going to the section's page. */
        $(function() {
            var $nav = $('#nav');

            // Whether the phone menu is showing, which menu.css signals with --nav-mode. (Checking
            // the CSS, rather than the screen width, means that this doesn't take over taps when
            // a browser has an older, cached menu.css that has no phone menu.)
            function phoneMenu() {
                return getComputedStyle(document.documentElement).getPropertyValue('--nav-mode').trim() === 'phone';
            }

            $('#nav-toggle').on('click', function() {
                $(this).attr('aria-expanded', $nav.toggleClass('open').hasClass('open'));
            });

            $nav.find('> li, li.past-years').each(function() {
                var $li = $(this);
                if (!$li.children('ul').length) {
                    return;
                }
                $li.children('a, .nav-label').on('click keydown', function(e) {
                    if (!phoneMenu() || (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ')) {
                        return;
                    }
                    e.preventDefault();
                    $(this).attr('aria-expanded', $li.toggleClass('open').hasClass('open'));
                });
            });
        });

        /* Create tabs and add dropdown fallback when tabs overflow. */
        // Wait until the DOM has loaded before querying the document
        $(document).ready(function() {
            $(".tabs").tabs();

            function debounce(fn, wait) {
                var t;
                return function() {
                    clearTimeout(t);
                    t = setTimeout(fn, wait);
                };
            }

            function ensureDropdown($tabs) {
                var $select = $tabs.children("select.tabs-dropdown");
                if ($select.length) {
                    return $select;
                }

                var $ul = $tabs.children("ul").first();
                $select = $('<select class="tabs-dropdown" aria-label="Select section"></select>');

                $ul.find("a").each(function(i) {
                    var $a = $(this);
                    $select.append($("<option></option>").val(i).text($a.text()));
                });

                $select.on("change", function() {
                    $tabs.tabs("option", "active", parseInt(this.value, 10));
                });

                $select.insertBefore($ul);

                $tabs.on("tabsactivate", function(e, ui) {
                    $select.val(ui.newTab.index());
                });

                return $select;
            }

            function updateLayout($tabs) {
                var $ul = $tabs.children("ul").first();
                var wasDropdown = $tabs.hasClass("tabs--dropdown");

                // Measure with the tabs visible; hiding them (dropdown mode) makes widths zero.
                if (wasDropdown) {
                    $tabs.removeClass("tabs--dropdown");
                }

                var overflow = $ul[0] && $ul[0].scrollWidth > $ul.innerWidth() + 1; // 1px buffer

                if (overflow) {
                    ensureDropdown($tabs);
                    $tabs.addClass("tabs--dropdown");
                } else {
                    $tabs.removeClass("tabs--dropdown");
                }

                // If we were already in dropdown mode and still are, no-op; otherwise state is set above.
            }

            $(".tabs").each(function() {
                var $tabs = $(this);
                updateLayout($tabs);
                $(window).on("resize", debounce(function() {
                    updateLayout($tabs);
                }, 150));
            });
        });

        /* Tooltips open on hover, where there's a mouse. On touch screens, the ones on links are
           left out, so that a tap goes straight to the link; the others open on a tap. */
        var canHover = window.matchMedia('(hover: hover)').matches;

        /* Help icon tooltips */
        $(function(){
            $(".help-icon-link").each(function() {
                var $link = $(this);
                var url = $link.data("help-url") || $link.attr("href");

                // Prevent the old popup behavior.
                $link.on("click", function(e){ e.preventDefault(); });

                $link.qtip({
                    content: {
                        text: function(event, api) {
                            var ajaxUrl = url.indexOf("?") === -1 ? url + "?ajax=1" : url + "&ajax=1";
                            $.ajax({ url: ajaxUrl })
                                .then(function(content) { api.set("content.text", content); })
                                .fail(function() { api.set("content.text", "Unable to load help."); });
                            return "Loading…";
                        }
                    },
                    position: { my: "top center", at: "bottom center", viewport: $(document.body), adjust: { method: "shift" } },
                    show: { event: canHover ? "mouseenter" : "click" },
                    hide: { event: canHover ? "mouseleave unfocus" : "unfocus", delay: 200, fixed: true },
                    style: { classes: "qtip-dark qtip-shadow" }
                });
            });
        });

        /* Truncate text at 500 characters of length. Written by "c_harm" and posted to Stack Overflow
        at http://stackoverflow.com/a/1199627/955342 */
        String.prototype.truncate = function(){
            var re = this.match(/^.{0,500}[\S]*/);
            var l = re[0].length;
            var re = re[0].replace(/\s$/,'');
            if(l < this.length)
                re = re + "&nbsp;. . . ";
            return re;
        }

        $(function(){
            /* Mentions of bill numbers. */
            $(canHover ? "a.balloon" : null).each(function() {

                /* Use the URL to determine the bill year and number. */
                var url = $(this).attr("href");
                var url_components = url.match(/bill\/(\d{4})\/(\w+)\//);
                var year = url_components[1];
                var bill_number = url_components[2];

                $(this).qtip({
                    tip: true,
                    hide: {
                        when: 'mouseout',
                        fixed: true,
                        delay: 100
                    },
                    position: {
                        at: "top center",
                        my: "bottom center"
                    },
                    style: {
                        width: 300,
                        tip: "bottom center",
                        classes: "qtip-bootstrap"
                    },
                    content: {
                        text: function(event, api) {
                            $.ajax({
                                url: '<?php echo API_URL; ?>1.1/bill/'+year+'/'+bill_number+'.json'
                            })
                            .then(function(data) {
                                // Set the tooltip content
                                var sponsor = '<a href="/legislator/' + data.chief_patron_shortname + '/">' + data.patron_name_formatted + '</a>'
                                var content;
                                if (data.summary) {
                                    content = sponsor + ': ' + data.summary.truncate();
                                }
                                api.set('content.text', content);
                            }, function(xhr, status, error) {
                                // Upon error
                                api.set('content.text', 'View bill');
                            });
                            
                            return 'Loading .&thinsp;.&thinsp;.'; // Set some initial text
                        }
                    }
                })
            });

            /* Mentions of legislators. */
            $(canHover ? "a.legislator" : null).each(function() {

                /* Use the URL to determine the bill year and number. */
                var url = $(this).attr("href");
                var url_components = url.match(/legislator\/(\w+)\//);
                var legislator = url_components[1];

                $(this).qtip({
                    tip: true,
                    hide: {
                        when: 'mouseout',
                        fixed: true,
                        delay: 100
                    },
                    position: {
                        at: "top center",
                        my: "bottom center"
                    },
                    style: {
                        width: 300,
                        tip: "bottom center",
                        classes: "qtip-bootstrap"
                    },
                    content: {
                        text: function(event, api) {
                            $.ajax({
                                url: '<?php echo API_URL; ?>1.1/legislator/'+legislator+'.json',
                            })
                            .then(function(data) {
                                // Set the tooltip content
                                var content = '<img src="/images/legislators/thumbnails/' + legislator + '.jpg" height="50" style="float: left; margin: 0 .5em .5em 0" \/>'
                                    + '<strong>' + data.name_formatted + '</strong></br >Represents: '
                                    + data.district_description + '<br>Took Office: ' + data.date_started;
                                api.set('content.text', content);
                            }, function(xhr, status, error) {
                                // Upon error
                                api.set('content.text', 'View legislator');
                            });
                            
                            return 'Loading .&thinsp;.&thinsp;.'; // Set some initial text
                        }
                    }
                })
            });

            /* Mentions of sections of the Code of Virginia. */
            $(canHover ? "a.code" : null).each(function() {

                /* Use the URL to determine the section number. */
                var url = $(this).attr("href");
                var url_components = url.match(/https:\/\/vacode.org\/(.*)\//);
                var section_number = url_components[1];

                $(this).qtip({
                    tip: true,
                    hide: {
                        when: 'mouseout',
                        fixed: true,
                        delay: 100
                    },
                    position: {
                        at: "top center",
                        my: "bottom center"
                    },
                    style: {
                        width: 300,
                        tip: "bottom center",
                        classes: "qtip-bootstrap"
                    },
                    content: {
                        text: function(event, api) {
                            $.ajax({
                                url: 'https://vacode.org/api/law/'+section_number+'/',
                                type: 'GET',
                                data: { fields: 'catch_line,ancestry', key: 'zxo8k592ztiwbgre' },
                                dataType: 'jsonp',
                            })
                            .then(function(section) {
                                // Set the tooltip content
                                if( section.ancestry instanceof Object ) {
                                    var content = '';
                                    for (key in section.ancestry) {
                                        var content = section.ancestry[key].name + ' → ' + content;
                                    }
                                }
                                var content = content + section.catch_line;
                                api.set('content.text', content);
                            }, function(xhr, status, error) {
                                // Upon error
                                api.set('content.text', 'View law');
                            });
                            
                            return 'Loading .&thinsp;.&thinsp;.'; // Set some initial text
                        }
                    }
                })
            });

            /* Words for which we have dictionary terms. */
            $("span.dictionary").each(function() {

                var term = $(this).text();
                $(this).qtip({
                    tip: true,
                    show: { event: canHover ? "mouseenter" : "click" },
                    hide: {
                        when: 'mouseout',
                        fixed: true,
                        delay: 100
                    },
                    position: {
                        at: "top center",
                        my: "bottom center"
                    },
                    style: {
                        width: 300,
                        tip: "bottom center",
                        classes: "qtip-bootstrap"
                    },
                    content: {
                        text: function(event, api) {
                            $.ajax({
                                url: 'https://vacode.org/api/dictionary/' + encodeURI(term) + '/',
                                type: 'GET',
                                data: { fields: 'definition,url,section_number,source', key: 'zxo8k592ztiwbgre', section: section_number },
                                dataType: 'jsonp',
                            })
                            .then(function(data) {
                                // Set the tooltip content
                                var content = data.definition;
                                if (data.section_number != null) {
                                    content = content + ' (<a href="http://vacode.org' + data.url + '">§&nbsp;' + data.section_number + '</a>)';
                                }
                                else if (data.source) {
                                    content = content + ' (Source: <a href="' + data.url + '">' + data.source + '</a>)';
                                }
                                api.set('content.text', content);
                            }, function(xhr, status, error) {
                                // Upon error
                                api.set('content.text', '');
                            });
                            
                            return 'Loading .&thinsp;.&thinsp;.'; // Set some initial text
                        }
                    }
                })
            });
        });

        /* Allow the sliding-down display of poll results. */
        $("#show-poll-results").click(function() {
            $("#poll-results").toggle('slow', function() {
                // Animation complete.
            });
        });

        // Photosynthesis sidebar
        $(document).ready(function() {

            // Show the sidebar if there's a portfolio hash
            if (typeof portfolios !== 'undefined') {

                $('#portfolio-sidebar').show();
                $('#portfolio-sidebar').tabSlideOut({'tabLocation':'right','action':'click'});

                // List all bills in all portfolios
                $.each( portfolios, function( index, portfolio_hash ) {
                    url = '<?php echo API_URL; ?>1.1/photosynthesis/' + portfolio_hash + '.json';
                    $.getJSON(url, function(data) {
                        $.each( data.bills, function( index, bill ) {
                            
                            var d = new Date(bill.date);
                            var date = (d.getMonth() + 1) + "/" + d.getDate() + "/" + d.getFullYear();

                            $( "#portfolio-list" ).append( '<div class="bill"><a href="' + bill.url + '">'
                                + bill.number.toUpperCase() + '</a>: ' + bill.catch_line + ' <span class="last-updated">Updated&nbsp;' + date + '</span></div>');
                        });
                    });
                });
                $( "#portfolio-list" ).append('<p><a href="/photosynthesis/">View your portfolio »</a></p>');
            }
        });

    </script>

</body>
</html>
