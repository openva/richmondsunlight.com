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
						The General Assembly is now in session.
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
								</ul>
							</li>
						</ul>
					</li>

					<li id="t-legislators"><a href="/legislators/" accesskey="l">Legislators</a>
						<ul>
						<li>House »
							<ul class="alphabetic">
							<li>A-C »
								<ul class="legislators">
								<li><a href="/legislator/lradams/">Del. Les Adams (R-Chatham)</a></li>
								<li><a href="/legislator/ldaird/">Del. Lashrecse D. Aird (D-Petersburg)</a></li>
								<li><a href="/legislator/tlaustin/">Del. Terry Austin (R-Buchanan)</a></li>
								<li><a href="/legislator/hsayala/">Del. Hala Ayala (D-Woodbridge)</a></li>
								<li><a href="/legislator/lbagby/">Del. Lamont Bagby (D-Richmond)</a></li>
								<li><a href="/legislator/rpbell/">Del. Dickie Bell (R-Staunton)</a></li>
								<li><a href="/legislator/jjbell/">Del. John Bell (D-Chantilly)</a></li>
								<li><a href="/legislator/rbbell/">Del. Rob Bell (R-Charlottesville)</a></li>
								<li><a href="/legislator/rtbloxom/">Del. Rob Bloxom (R-Accomack)</a></li>
								<li><a href="/legislator/jmbourne/">Del. Jeff Bourne (D-Richmond)</a></li>
								<li><a href="/legislator/embrewer/">Del. Emily Brewer (R-Suffolk)</a></li>
								<li><a href="/legislator/dlbulova/">Del. David Bulova (D-Fairfax)</a></li>
								<li><a href="/legislator/kjbyron/">Del. Kathy Byron (R-Lynchburg)</a></li>
								<li><a href="/legislator/jlcampbell/">Del. Jeff Campbell (R-Marion)</a></li>
								<li><a href="/legislator/rrcampbell/">Del. Ronnie Campbell (R-Raphine)</a></li>
								<li><a href="/legislator/bbcarr/">Del. Betsy Carr (D-Richmond)</a></li>
								<li><a href="/legislator/jcarrollfoy/">Del. Jennifer Carroll Foy (D-Woodbridge)</a></li>
								<li><a href="/legislator/ljcarter/">Del. Lee Carter (D-Manassas)</a></li>
								<li><a href="/legislator/mlcole/">Del. Mark Cole (R-Fredericksburg)</a></li>
								<li><a href="/legislator/cecollins/">Del. Chris Collins (R-Winchester)</a></li>
								<li><a href="/legislator/mkcox/">Del. Kirk Cox (R-Colonial Heights)</a></li>
								</ul>
							</li>
							<li>D-H »
								<ul class="legislators">
								<li><a href="/legislator/grdavis/">Del. Glenn Davis (R-Virginia Beach)</a></li>
								<li><a href="/legislator/kkdelaney/">Del. Karrie Delaney (D-Centreville)</a></li>
								<li><a href="/legislator/jeedmunds/">Del. James Edmunds (R-South Boston)</a></li>
								<li><a href="/legislator/cmfariss/">Del. Matt Fariss (R-Rustburg)</a></li>
								<li><a href="/legislator/erfiller-corn/">Del. Eileen Filler-Corn (D-Fairfax Station)</a></li>
								<li><a href="/legislator/hffowler/">Del. Buddy Fowler (R-Ashland)</a></li>
								<li><a href="/legislator/kkfowler/">Del. Kelly Fowler (D-Virginia Beach)</a></li>
								<li><a href="/legislator/njfreitas/">Del. Nick Freitas (R-Culpeper)</a></li>
								<li><a href="/legislator/tsgarrett/">Del. Scott Garrett (R-Lynchburg)</a></li>
								<li><a href="/legislator/ctgilbert/">Del. Todd Gilbert (R-Woodstock)</a></li>
								<li><a href="/legislator/gwgooditis/">Del. Wendy Gooditis (D-Clarke)</a></li>
								<li><a href="/legislator/erguzman/">Del. Elizabeth Guzman (D-Dale City)</a></li>
								<li><a href="/legislator/cehayes/">Del. Cliff Hayes (D-Chesapeake)</a></li>
								<li><a href="/legislator/cthead/">Del. Chris Head (R-Roanoke)</a></li>
								<li><a href="/legislator/gchelsel/">Del. Gordon Helsel (R-Poquoson)</a></li>
								<li><a href="/legislator/seheretick/">Del. Steve Heretick (D-Portsmouth)</a></li>
								<li><a href="/legislator/clherring/">Del. Charniele Herring (D-Alexandria)</a></li>
								<li><a href="/legislator/mkhodges/">Del. Keith Hodges (R-Urbanna)</a></li>
								<li><a href="/legislator/pahope/">Del. Patrick Hope (D-Arlington)</a></li>
								<li><a href="/legislator/tdhugo/">Del. Tim Hugo (R-Centreville)</a></li>
								<li><a href="/legislator/clhurst/">Del. Chris Hurst (D-Blacksburg)</a></li>
								</ul>
							</li>
							<li>I-L »
								<ul class="legislators">
								<li><a href="/legislator/reingram/">Del. Riley Ingram (R-Hopewell)</a></li>
								<li><a href="/legislator/mjames/">Del. Matthew James (D-Portsmouth)</a></li>
								<li><a href="/legislator/scjones/">Del. Chris Jones (R-Suffolk)</a></li>
								<li><a href="/legislator/jcjones/">Del. Jay Jones (D-Norfolk)</a></li>
								<li><a href="/legislator/mlkeam/">Del. Mark Keam (D-Vienna)</a></li>
								<li><a href="/legislator/tgkilgore/">Del. Terry Kilgore (R-Gate City)</a></li>
								<li><a href="/legislator/bdknight/">Del. Barry Knight (R-Virginia Beach)</a></li>
								<li><a href="/legislator/lkkory/">Del. Kaye Kory (D-Falls Church)</a></li>
								<li><a href="/legislator/pekrizek/">Del. Paul Krizek (D-Alexandria)</a></li>
								<li><a href="/legislator/rslandes/">Del. Steve Landes (R-Weyers Cave)</a></li>
								<li><a href="/legislator/dalarock/">Del. Dave LaRock (R-Loudoun)</a></li>
								<li><a href="/legislator/jaleftwich/">Del. Jay Leftwich (R-Chesapeake)</a></li>
								<li><a href="/legislator/mhlevine/">Del. Mark Levine (D-Alexandria)</a></li>
								<li><a href="/legislator/jclindsey/">Del. Joe Lindsey (D-Norfolk)</a></li>
								<li><a href="/legislator/ahlopez/">Del. Alfonso Lopez (D-Arlington)</a></li>
								</ul>
							</li>
							<li>M-R »
								<ul class="legislators">
								<li><a href="/legislator/dwmarshall/">Del. Danny Marshall (R-Danville)</a></li>
								<li><a href="/legislator/jjmcguire/">Del. John McGuire (R-Glen Allen)</a></li>
								<li><a href="/legislator/jpmcnamara/">Del. Joe McNamara (R-Roanoke)</a></li>
								<li><a href="/legislator/dlmcquinn/">Del. Delores McQuinn (D-Richmond)</a></li>
								<li><a href="/legislator/jsmiyares/">Del. Jason Miyares (R-Virginia Beach)</a></li>
								<li><a href="/legislator/jwmorefield/">Del. Will Morefield (R-North Tazewell)</a></li>
								<li><a href="/legislator/mpmullin/">Del. Mike Mullin (D-Newport News)</a></li>
								<li><a href="/legislator/kjmurphy/">Del. Kathleen Murphy (D-McLean)</a></li>
								<li><a href="/legislator/idoquinn/">Del. Israel O'Quinn (R-Bristol)</a></li>
								<li><a href="/legislator/rdorrock/">Del. Bobby Orrock (R-Thornburg)</a></li>
								<li><a href="/legislator/ckpeace/">Del. Chris Peace (R-Mechanicsville)</a></li>
								<li><a href="/legislator/tepillion/">Del. Todd E. Pillion (R-Abingdon)</a></li>
								<li><a href="/legislator/krplum/">Del. Ken Plum (D-Reston)</a></li>
								<li><a href="/legislator/blpogge/">Del. Brenda Pogge (R-Williamsburg)</a></li>
								<li><a href="/legislator/cdpoindexter/">Del. Charles Poindexter (R-Glade Hill)</a></li>
								<li><a href="/legislator/msprice/">Del. Cia Price (D-Newport News)</a></li>
								<li><a href="/legislator/mbransone/">Del. Margaret Ransone (R-Kinsale)</a></li>
								<li><a href="/legislator/srasoul/">Del. Sam Rasoul (D-Roanoke)</a></li>
								<li><a href="/legislator/dareid/">Del. David Reid (D-Loudoun)</a></li>
								<li><a href="/legislator/rlrobinson/">Del. Roxann Robinson (R-Chesterfield)</a></li>
								<li><a href="/legislator/dhrodman/">Del. Debra Rodman (D-Henrico)</a></li>
								<li><a href="/legislator/daroem/">Del. Danica Roem (D-Manassas Park)</a></li>
								<li><a href="/legislator/lnrush/">Del. Nick Rush (R-Christiansburg)</a></li>
								</ul>
							</li>
							<li>S-Z »
								<ul class="legislators">
								<li><a href="/legislator/mdsickles/">Del. Mark Sickles (D-Alexandria)</a></li>
								<li><a href="/legislator/mbsimon/">Del. Marcus Simon (D-Falls Church)</a></li>
								<li><a href="/legislator/cpstolle/">Del. Chris Stolle (R-Virginia Beach)</a></li>
								<li><a href="/legislator/rcsullivan/">Del. Rip Sullivan (D-Arlington)</a></li>
								<li><a href="/legislator/rmthomas/">Del. Bob Thomas (R-Stafford)</a></li>
								<li><a href="/legislator/letorian/">Del. Luke Torian (D-Woodbridge)</a></li>
								<li><a href="/legislator/djtoscano/">Del. David Toscano (D-Charlottesville)</a></li>
								<li><a href="/legislator/kkltran/">Del. Kathy Tran (D-Springfield)</a></li>
								<li><a href="/legislator/cbturpin/">Del. Cheryl Turpin (D-Virginia Beach)</a></li>
								<li><a href="/legislator/rctyler/">Del. Roslyn Tyler (D-Jarratt)</a></li>
								<li><a href="/legislator/stvanvalkenburg/">Del. Schuyler VanValkenburg (D-Henrico)</a></li>
								<li><a href="/legislator/jaward/">Del. Jeion Ward (D-Hampton)</a></li>
								<li><a href="/legislator/rlware/">Del. Lee Ware (R-Powhatan)</a></li>
								<li><a href="/legislator/vewatts/">Del. Vivian Watts (D-Annandale)</a></li>
								<li><a href="/legislator/mjwebert/">Del. Michael Webert (R-Marshall)</a></li>
								<li><a href="/legislator/aowilt/">Del. Tony Wilt (R-Harrisonburg)</a></li>
								<li><a href="/legislator/tcwright/">Del. Tommy Wright (R-Victoria)</a></li>
								<li><a href="/legislator/deyancey/">Del. David Yancey (R-Newport News)</a></li>
								</ul>
							</li>
							</ul>
						</li>
						<li>Senate »
							<ul class="alphabetic">
								<li>A-M »
									<ul class="legislators">
									<li><a href="/legislator/glbarker/">Sen. George Barker (D-Alexandria)</a></li>
									<li><a href="/legislator/rhblack/">Sen. Dick Black (R-Leesburg)</a></li>
									<li><a href="/legislator/jbboysko/">Sen. Jennifer Boysko (D-Herndon)</a></li>
									<li><a href="/legislator/cwcarrico/">Sen. Bill Carrico (R-Grayson)</a></li>
									<li><a href="/legislator/abchafin/">Sen. Ben Chafin (R-Lebanon)</a></li>
									<li><a href="/legislator/afchase/">Sen. Amanda Chase (R-Midlothian)</a></li>
									<li><a href="/legislator/jacosgrove/">Sen. John Cosgrove (R-Chesapeake)</a></li>
									<li><a href="/legislator/rrdance/">Sen. Roz Dance (D-Petersburg)</a></li>
									<li><a href="/legislator/rcdeeds/">Sen. Creigh Deeds (D-Bath)</a></li>
									<li><a href="/legislator/wrdesteph/">Sen. Bill DeSteph (R-Virginia Beach)</a></li>
									<li><a href="/legislator/ssdunnavant/">Sen. Siobhan Dunnavant (R-Henrico)</a></li>
									<li><a href="/legislator/apebbin/">Sen. Adam Ebbin (D-Alexandria)</a></li>
									<li><a href="/legislator/jsedwards/">Sen. John Edwards (D-Roanoke)</a></li>
									<li><a href="/legislator/bafavola/">Sen. Barbara Favola (D-Arlington)</a></li>
									<li><a href="/legislator/ewhanger/">Sen. Emmett Hanger (R-Mount Solon)</a></li>
									<li><a href="/legislator/jdhowell/">Sen. Janet Howell (D-Reston)</a></li>
									<li><a href="/legislator/lwlewis/">Sen. Lynwood Lewis (D-Accomac)</a></li>
									<li><a href="/legislator/melocke/">Sen. Mamie Locke (D-Hampton)</a></li>
									<li><a href="/legislator/lllucas/">Sen. Louise Lucas (D-Portsmouth)</a></li>
									<li><a href="/legislator/dwmarsden/">Sen. Dave Marsden (D-Burke)</a></li>
									<li><a href="/legislator/tmmason/">Sen. Monty Mason (D-Williamsburg)</a></li>
									<li><a href="/legislator/jlmcclellan/">Sen. Jennifer McClellan (D-Richmond)</a></li>
									<li><a href="/legislator/rtmcdougle/">Sen. Ryan McDougle (R-Mechanicsville)</a></li>
									<li><a href="/legislator/jsmcpike/">Sen. Jeremy McPike (D-Dale City)</a></li>
									</ul>
								</li>
								<li>N-Z »
									<ul class="legislators">
									<li><a href="/legislator/sdnewman/">Sen. Steve Newman (R-Forest)</a></li>
									<li><a href="/legislator/tknorment/">Sen. Tommy Norment (R-Williamsburg)</a></li>
									<li><a href="/legislator/mdobenshain/">Sen. Mark Obenshain (R-Harrisonburg)</a></li>
									<li><a href="/legislator/mjpeake/">Sen. Mark Peake (R-Lynchburg)</a></li>
									<li><a href="/legislator/jcpetersen/">Sen. Chap Petersen (D-Fairfax)</a></li>
									<li><a href="/legislator/bereeves/">Sen. Bryce Reeves (R-Spotsylvania)</a></li>
									<li><a href="/legislator/fmruff/">Sen. Frank Ruff (R-Clarksville)</a></li>
									<li><a href="/legislator/rlsaslaw/">Sen. Dick Saslaw (D-Springfield)</a></li>
									<li><a href="/legislator/lspruill/">Sen. Lionell Spruill (D-Chesapeake)</a></li>
									<li><a href="/legislator/wmstanley/">Sen. Bill Stanley (R-Moneta)</a></li>
									<li><a href="/legislator/rhstuart/">Sen. Richard Stuart (R-Westmoreland)</a></li>
									<li><a href="/legislator/ghsturtevant/">Sen. Glen Sturtevant (R-Midlothian)</a></li>
									<li><a href="/legislator/drsuetterlein/">Sen. David Suetterlein (R-Salem)</a></li>
									<li><a href="/legislator/sasurovell/">Sen. Scott Surovell (D-Mount Vernon)</a></li>
									<li><a href="/legislator/jhvogel/">Sen. Jill Holtzman Vogel (R-Winchester)</a></li>
									<li><a href="/legislator/fwwagner/">Sen. Frank Wagner (R-Virginia Beach)</a></li>
									<li><a href="/legislator/jtwexton/">Sen. Jennifer Wexton (D-Leesburg)</a></li>
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
				re = re + "&nbsp;.&thinsp;.&thinsp;.&thinsp;";
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
								var content = '<a href="/legislator/' + data.chief_patron_id + '/">' + data.patron_name_formatted + '</a>: ' + data.summary.truncate();
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
	
		<?php
		if (isset($_SESSION['portfolios']))
		{

			// Make our portfolio IDs available to JavaScript.
			$page_body = 'var portfolios = [];';
			foreach ($_SESSION['portfolios'] as $portfolio)
			{
				$page_body .= 'portfolios.push("' . $portfolio['hash'] . '");';
			}
			$page_body .= '</script>';
		?>

		// Photosynthesis sidebar
		$(document).ready(function() {

			// Show the sidebar if there's a portfolio hash
			if ($.isArray(portfolio_hash)) {

				$('#portfolio-sidebar').tabSlideOut({'tabLocation':'right','action':'click'});

				// List all bills in all portfolios
				$.each( portfolios, function( index, portfolio_hash ) {
				url = '<?php echo API_URL; ?>1.1/photosynthesis/' + portfolio_hash + '.json';
					console.log(url);
					$.getJSON(url, function(data) {
						$.each( data.bills, function( index, bill ) {
							$( "#portfolio-list" ).append( '<div class="bill"><a href="' + bill.url + '">'
								+ bill.number + '</a>: ' + bill.catch_line + '</div>');
						});
					});
				});
			}
		});

	</script>

</body>
</html>
