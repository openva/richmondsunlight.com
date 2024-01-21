<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta charset="utf-8" />
<meta name=viewport content="width=device-width, initial-scale=1">
<title>%browser_title%</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#790806"/>
<link rel="stylesheet" href="/css/new/screen.css" media="screen" />
<link rel="stylesheet" href="/css/new/print.css" media="print" />
<link rel="stylesheet" href="/css/page-elements.css" media="screen" />
<link rel="stylesheet" href="/css/jquery-ui.theme.min.css" media="screen">
<link rel="stylesheet" href="/js/vendor/qtip2/dist/jquery.qtip.min.css" media="screen">
<link rel="stylesheet" href="/js/vendor/jquery.tabSlideOut.js/jquery.tabSlideOut.css"> 
    
<!--<link media="only screen and (max-device-width: 480px), only screen and (min-device-width: 560px) and (max-device-width: 1136px) and (-webkit-min-device-pixel-ratio: 2)"
    href="/css/iphone.css" rel="stylesheet" />-->
<script src="/js/vendor/jquery/dist/jquery.min.js"></script>
<script src="/js/vendor/jquery-ui/jquery-ui.min.js"></script>
<script src="/js/functions.js"></script>
<script src="/js/vendor/qtip2/dist/jquery.qtip.min.js"></script>
<script src="/js/vendor/jquery.tabSlideOut.js/jquery.tabSlideOut.js"></script>

<!-- For IE 11, Chrome, Firefox, Safari, Opera -->
<link rel="icon" type="image/png" href="/images/favicons/16.png" sizes="16x16" />
<link rel="icon" type="image/png" href="/images/favicons/32.png" sizes="32x32" />
<link rel="icon" type="image/png" href="/images/favicons/48.png" sizes="48x48" />
<link rel="icon" type="image/png" href="/images/favicons/62.png" sizes="62x62" />
<link rel="icon" type="image/png" href="/images/favicons/192.png" sizes="192x192" />

<!-- Add to Home Screen -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="Richmond Sunlight">

<!-- iOS Touch Icons -->
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/76.png" sizes="76x76" />
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/120.png" sizes="120x120" />
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/152.png" sizes="152x152" />
<link rel="apple-touch-icon" type="image/png" href="/images/favicons/apple-touch-icons/180.png" sizes="180x180" />

<!-- Safari Pinned Site -->
<link rel="mask-icon" href="/images/favicons/safari_icon.svg" />

<?php
    # Include the below JavaScript, but only if the browser is IE. We do an Opera check because
    # Opera can include the text "MSIE" in its user agent string.
if (
            mb_strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')
            &&
            !mb_strpos($_SERVER['HTTP_USER_AGENT'], 'Opera')
) {
    ?>
<script pagespeed_no_defer="">
    <!--//--><![CDATA[//><!--
    activateMenu = function(nav) {
        if(document.all && document.getElementById(nav).currentStyle){
            // only MSIE supports document.all
            var navroot = document.getElementById(nav);

            /* Get all the list items within the menu */
            var lis=navroot.getElementsByTagName("LI");
            for(i=0;i<lis.length;i++){
                /* If the LI has another menu level */
                if(lis[i].lastChild.tagName=="UL"){
                    /* assign the function to the LI */
                    lis[i].onmouseover=function(){
                        /* display the inner menu */
                        this.lastChild.style.display="block";
                    }
                    lis[i].onmouseout=function(){
                        this.lastChild.style.display="none";
                    }
                }
            }
        }
    }
    window.onload=function(){
        // pass the function the id of the top level UL
        // remove one, when only using one menu
        activateMenu('nav');
    }
    //--><!]]>
</script>
    <?php
    /* End the menu JavaScript conditional */
}
?>

%html_head%
</head>
<body%body_tag%>
    <div id="page-wrap">
        <div id="page">
            <header>
            <div id="header">
                <div id="logo">
                    <a href="/"><img src="/images/templates/new/richmond-sunlight-logo.png"
                        alt="Richmond Sunlight Logo" title="Richmond Sunlight" width="274" height="88"
                        pagespeed_no_transform /></a>
                </div>

                <div id="from-and-search">
                    <div id="recommendations">
                        %recommended_bills%
                    </div>
                    <form id="search" method="get" action="/search/">
                        <label for="search-box"><img src="/images/templates/new/search-label.gif"
                            width="56" height="20" alt="Search" /></label>
                        <input type="search" name="q" id="search-box" />
                        <input type="image" src="/images/templates/new/go-search.gif" alt="Go" />
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
                <ul id="nav">
                    <li id="t-home"><a href="/" accesskey="h">Home</a></li>
                    <li id="t-bills"><a href="/bills/" accesskey="b">Bills</a>
                        <ul>
                            <li><a href="/bills/topic/">By Topic</a></li>
                            <li><a href="/bills/introduced/">Newest</a></li>
                            <li><a href="/bills/activity/">Activity</a></li>
                            <li><a href="/bills/#house">House</a></li>
                            <li><a href="/bills/#senate">Senate</a></li>
                            <li>Past Years »
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
                                </ul>
                            </li>
                        </ul>
                    </li>
                    
                    <li id="t-legislators"><a href="/legislators/" accesskey="l">Legislators</a>
                        <ul>
                            <li>House »
                                <ul class="alphabetic">
                                <li>A–B »
                                    <ul class="legislators">
                                        <li><a href="/legislator/lradams/">Les Adams</a></li>
                                        <li><a href="/legislator/bganthony/">Bonita Anthony</a></li>
                                        <li><a href="/legislator/jearnold/">Jed Arnold</a></li>
                                        <li><a href="/legislator/aqaskew/">Alex Askew</a></li>
                                        <li><a href="/legislator/tlaustin/">Terry Austin</a></li>
                                        <li><a href="/legislator/jsballard/">Jason Ballard</a></li>
                                        <li><a href="/legislator/aebatten/">Amanda Batten</a></li>
                                        <li><a href="/legislator/ebbennett-parker/">Elizabeth Bennett-Parker</a></li>
                                        <li><a href="/legislator/rtbloxom/">Rob Bloxom</a></li>
                                        <li><a href="/legislator/dllbolling/">Destiny LeVere Bolling</a></li>
                                        <li><a href="/legislator/dlbulova/">David Bulova</a></li>
                                    </ul>
                                </li>
                                <li>C–F »
                                    <ul class="legislators">
                                        <li><a href="/legislator/kcallsen/">Katrina Callsen</a></li>
                                        <li><a href="/legislator/ehcampbell/">Ellen Campbell</a></li>
                                        <li><a href="/legislator/bbcarr/">Betsy Carr</a></li>
                                        <li><a href="/legislator/macherry/">Mike Cherry</a></li>
                                        <li><a href="/legislator/neclark/">Nadarius Clark</a></li>
                                        <li><a href="/legislator/ljcohen/">Laura Jane Cohen</a></li>
                                        <li><a href="/legislator/jgcole/">Josh Cole</a></li>
                                        <li><a href="/legislator/accordoza/">A.C. Cordoza</a></li>
                                        <li><a href="/legislator/rccousins/">Rae Cousins</a></li>
                                        <li><a href="/legislator/cecoyner/">Carrie Coyner</a></li>
                                        <li><a href="/legislator/wpdavis/">Will Davis</a></li>
                                        <li><a href="/legislator/kkdelaney/">Karrie Delaney</a></li>
                                        <li><a href="/legislator/mlearley/">Mark Earley</a></li>
                                        <li><a href="/legislator/nbennis/">N. Baxter Ennis</a></li>
                                        <li><a href="/legislator/mfeggans/">Michael Feggans</a></li>
                                        <li><a href="/legislator/hffowler/">Buddy Fowler</a></li>
                                        <li><a href="/legislator/kkfowler/">Kelly Fowler</a></li>
                                        <li><a href="/legislator/njfreitas/">Nick Freitas</a></li>
                                    </ul>
                                </li>
                                <li>G–J »
                                    <ul class="legislators">
                                        <li><a href="/legislator/ddgardner/">Debra Gardner</a></li>
                                        <li><a href="/legislator/tagarrett/">Tom Garrett</a></li>
                                        <li><a href="/legislator/ctgilbert/">Todd Gilbert</a></li>
                                        <li><a href="/legislator/jhglass/">Jackie Glass</a></li>
                                        <li><a href="/legislator/wcgreen/">Chad Green</a></li>
                                        <li><a href="/legislator/tgriffin/">Tim Griffin</a></li>
                                        <li><a href="/legislator/cehayes/">Cliff Hayes</a></li>
                                        <li><a href="/legislator/dihlemer/">Dan Helmer</a></li>
                                        <li><a href="/legislator/rahenson/">Rozia Henson</a></li>
                                        <li><a href="/legislator/pmhernandez/">Phil Hernandez</a></li>
                                        <li><a href="/legislator/clherring/">Charniele Herring</a></li>
                                        <li><a href="/legislator/gmhiggins/">Geary Higgins</a></li>
                                        <li><a href="/legislator/mkhodges/">Keith Hodges</a></li>
                                        <li><a href="/legislator/pahope/">Patrick Hope</a></li>
                                        <li><a href="/legislator/mjjones/">Michael Jones</a></li>
                                    </ul>
                                </li>
                                <li>K–M »
                                    <ul class="legislators">
                                        <li><a href="/legislator/hpkent/">Hillary Pugh Kent</a></li>
                                        <li><a href="/legislator/kkeys-gamarra/">Karen Keys-Gamarra</a></li>
                                        <li><a href="/legislator/tgkilgore/">Terry Kilgore</a></li>
                                        <li><a href="/legislator/cpking/">Candi King</a></li>
                                        <li><a href="/legislator/bdknight/">Barry Knight</a></li>
                                        <li><a href="/legislator/pekrizek/">Paul Krizek</a></li>
                                        <li><a href="/legislator/ajlaufer/">Amy Laufer</a></li>
                                        <li><a href="/legislator/jaleftwich/">Jay Leftwich</a></li>
                                        <li><a href="/legislator/ahlopez/">Alfonso Lopez</a></li>
                                        <li><a href="/legislator/itlovejoy/">Ian Lovejoy</a></li>
                                        <li><a href="/legislator/memaldonado/">Michelle Maldonado</a></li>
                                        <li><a href="/legislator/dwmarshall/">Danny Marshall</a></li>
                                        <li><a href="/legislator/fjmartinez/">Marty Martinez</a></li>
                                        <li><a href="/legislator/aymcclure/">Adele McClure</a></li>
                                        <li><a href="/legislator/jpmcnamara/">Joe McNamara</a></li>
                                        <li><a href="/legislator/dlmcquinn/">Delores McQuinn</a></li>
                                        <li><a href="/legislator/pvmilde/">Paul Milde</a></li>
                                        <li><a href="/legislator/jwmorefield/">Will Morefield</a></li>
                                    </ul>
                                </li>
                                <li>N–S »
                                    <ul class="legislators">
                                        <li><a href="/legislator/idoquinn/">Israel O'Quinn</a></li>
                                        <li><a href="/legislator/doates/">Delores Oates</a></li>
                                        <li><a href="/legislator/jcobenshain/">Chris Obenshain</a></li>
                                        <li><a href="/legislator/rdorrock/">Bobby Orrock</a></li>
                                        <li><a href="/legislator/dlowen/">David Owen</a></li>
                                        <li><a href="/legislator/msprice/">Cia Price</a></li>
                                        <li><a href="/legislator/srasoul/">Sam Rasoul</a></li>
                                        <li><a href="/legislator/arreaser/">Atoosa Reaser</a></li>
                                        <li><a href="/legislator/dareid/">David Reid</a></li>
                                        <li><a href="/legislator/csrunion/">Chris Runion</a></li>
                                        <li><a href="/legislator/dlscott/">Don Scott</a></li>
                                        <li><a href="/legislator/pascott/">Phil Scott</a></li>
                                        <li><a href="/legislator/hmseibold/">Holly Seibold</a></li>
                                        <li><a href="/legislator/bdsewell/">Briana Sewell</a></li>
                                        <li><a href="/legislator/ishin/">Irene Shin</a></li>
                                        <li><a href="/legislator/mdsickles/">Mark Sickles</a></li>
                                        <li><a href="/legislator/mbsimon/">Marcus Simon</a></li>
                                        <li><a href="/legislator/masimonds/">Shelly Simonds</a></li>
                                        <li><a href="/legislator/ksrinivasan/">Kannan Srinivasan</a></li>
                                        <li><a href="/legislator/rcsullivan/">Rip Sullivan</a></li>
                                    </ul>
                                </li>
                                <li>T–Z »
                                    <ul class="legislators">
                                        <li><a href="/legislator/aftata/">Anne Ferrell Tata</a></li>
                                        <li><a href="/legislator/kataylor/">Kim Taylor</a></li>
                                        <li><a href="/legislator/jethomas/">Josh Thomas</a></li>
                                        <li><a href="/legislator/letorian/">Luke Torian</a></li>
                                        <li><a href="/legislator/kkltran/">Kathy Tran</a></li>
                                        <li><a href="/legislator/howachsmann/">Otto Wachsmann</a></li>
                                        <li><a href="/legislator/wswalker/">Wendell Walker</a></li>
                                        <li><a href="/legislator/jaward/">Jeion Ward</a></li>
                                        <li><a href="/legislator/rlware/">Lee Ware</a></li>
                                        <li><a href="/legislator/vewatts/">Vivian Watts</a></li>
                                        <li><a href="/legislator/mjwebert/">Michael Webert</a></li>
                                        <li><a href="/legislator/wdwiley/">Bill Wiley</a></li>
                                        <li><a href="/legislator/rtwillett/">Rodney Willett</a></li>
                                        <li><a href="/legislator/wmwilliams/">Wren Williams</a></li>
                                        <li><a href="/legislator/aowilt/">Tony Wilt</a></li>
                                        <li><a href="/legislator/tcwright/">Tommy Wright</a></li>
                                        <li><a href="/legislator/sawyatt/">Scott Wyatt</a></li>
                                        <li><a href="/legislator/ezehr/">Eric Zehr</a></li>
                                    </ul>
                                </li>
                                </ul>
                            </li>
                            <li>Senate »
                                <ul class="alphabetic">
                                    <li>A–H »
                                        <ul class="legislators">
                                            <li><a href="/legislator/ldaird/">Lashrecse D. Aird</a></li>
                                            <li><a href="/legislator/lbagby/">Lamont Bagby</a></li>
                                            <li><a href="/legislator/jbboysko/">Jennifer Boysko</a></li>
                                            <li><a href="/legislator/embrewer/">Emily Brewer</a></li>
                                            <li><a href="/legislator/cncraig/">Christie New Craig</a></li>
                                            <li><a href="/legislator/rcdeeds/">Creigh Deeds</a></li>
                                            <li><a href="/legislator/wrdesteph/">Bill DeSteph</a></li>
                                            <li><a href="/legislator/jdddiggs/">Danny Diggs</a></li>
                                            <li><a href="/legislator/tadurant/">Tara Durant</a></li>
                                            <li><a href="/legislator/apebbin/">Adam Ebbin</a></li>
                                            <li><a href="/legislator/bafavola/">Barbara Favola</a></li>
                                            <li><a href="/legislator/jcfoy/">Jennifer Carroll Foy</a></li>
                                            <li><a href="/legislator/tffrench/">Timmy French</a></li>
                                            <li><a href="/legislator/awgraves/">Angelia Williams Graves</a></li>
                                            <li><a href="/legislator/tthackworth/">Travis Hackworth</a></li>
                                            <li><a href="/legislator/gfhashmi/">Ghazala Hashmi</a></li>
                                            <li><a href="/legislator/cthead/">Chris Head</a></li>
                                        </ul>
                                    </li>
                                    <li>I–R »
                                        <ul class="legislators">
                                            <li><a href="/legislator/melocke/">Mamie Locke</a></li>
                                            <li><a href="/legislator/lllucas/">Louise Lucas</a></li>
                                            <li><a href="/legislator/dwmarsden/">Dave Marsden</a></li>
                                            <li><a href="/legislator/rtmcdougle/">Ryan McDougle</a></li>
                                            <li><a href="/legislator/jjmcguire/">John McGuire</a></li>
                                            <li><a href="/legislator/jsmcpike/">Jeremy McPike</a></li>
                                            <li><a href="/legislator/mdobenshain/">Mark Obenshain</a></li>
                                            <li><a href="/legislator/mjpeake/">Mark Peake</a></li>
                                            <li><a href="/legislator/sgpekarsky/">Stella Pekarsky</a></li>
                                            <li><a href="/legislator/rwperry/">Russet Perry</a></li>
                                            <li><a href="/legislator/tepillion/">Todd E. Pillion</a></li>
                                            <li><a href="/legislator/bereeves/">Bryce Reeves</a></li>
                                            <li><a href="/legislator/daroem/">Danica Roem</a></li>
                                            <li><a href="/legislator/arrouse/">Aaron Rouse</a></li>
                                            <li><a href="/legislator/fmruff/">Frank Ruff</a></li>
                                        </ul>
                                    </li>
                                    <li>S–Z »
                                        <ul class="legislators">
                                            <li><a href="/legislator/sasalim/">Saddam Azlan Salim</a></li>
                                            <li><a href="/legislator/wmstanley/">Bill Stanley</a></li>
                                            <li><a href="/legislator/rhstuart/">Richard Stuart</a></li>
                                            <li><a href="/legislator/ghsturtevant/">Glen Sturtevant</a></li>
                                            <li><a href="/legislator/ssubramanyam/">Suhas Subramanyam</a></li>
                                            <li><a href="/legislator/drsuetterlein/">David Suetterlein</a></li>
                                            <li><a href="/legislator/sasurovell/">Scott Surovell</a></li>
                                            <li><a href="/legislator/stvanvalkenburg/">Schuyler VanValkenburg</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>

                        <li><a href="/legislators/detailed/">Detailed Listing</a></li>
                        <li><a href="/committees/">Committees</a></li>
                        <li><a href="/your-legislators/">Your Legislators</a></li>
                        </ul>
                    </li>
                    <li id="t-tools">Tools
                        <ul>
                        <li><a href="/photosynthesis/" accesskey="p">Photosynthesis</a></li>
                        <li><a href="/photosynthesis/portfolios/">Public Portfolios</a></li>
                        <li><a href="/about/api/">API</a></li>
                        <li><a href="/downloads/">Bulk Downloads</a></li>
                        </ul>
                    </li>
                    <li id="t-video"><a href="/minutes/" accesskey="v">Video</a>
                        <ul>
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

    <script pagespeed_no_defer="">
        /* Create tabs. */
        // Wait until the DOM has loaded before querying the document
        $(document).ready(function() {
            $(".tabs").tabs();
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
            $("a.balloon").each(function() {

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
                        tip: "bottom center"
                    },
                    content: {
                        text: function(event, api) {
                            $.ajax({
                                url: '<?php echo API_URL; ?>1.1/bill/'+year+'/'+bill_number+'.json'
                            })
                            .then(function(data) {
                                // Set the tooltip content
                                var content = '<a href="/legislator/' + data.chief_patron_shortname + '/">' + data.patron_name_formatted + '</a>: ' + data.summary.truncate();
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
            $("a.legislator").each(function() {

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
                        tip: "bottom center"
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
                                    + data.district_description + '<br />Took Office: ' + data.date_started;
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
            $("a.code").each(function() {

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
                        tip: "bottom center"
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
                        tip: "bottom center"
                    },


                    content: {
                        text: function(event, api) {
                            $.ajax({
                                url: 'https://vacode.org/api/dictionary/' + encodeURI(term) + '/',
                                type: 'GET',
                                data: { fields: 'catch_line,ancestry', key: 'zxo8k592ztiwbgre' },
                                dataType: 'jsonp',
                            })
                            .then(function(data) {
                                // Set the tooltip content
                                var content = data.definition;
                                if (data.section_number != null) {
                                    content = content + ' (<a href="' + data.url + '">§&nbsp;' + data.section_number + '</a>)';
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
                                + bill.number + '</a>: ' + bill.catch_line + ' <span class="last-updated">Updated&nbsp;' + date + '</span></div>');
                        });
                    });
                });
                $( "#portfolio-list" ).append('<p><a href="/photosynthesis/">View your portfolio »</a></p>');
            }
        });

    </script>

</body>
</html>
