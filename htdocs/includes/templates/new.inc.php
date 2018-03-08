<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta charset="utf-8" />
<meta name=viewport content="width=device-width, initial-scale=1">
<title>%browser_title%</title>
<link rel="manifest" href="/manifest.json">
<link rel="stylesheet" href="/css/new/screen.css" type="text/css" media="screen" />
<link rel="stylesheet" href="/css/new/print.css" type="text/css" media="print" />
<link rel="stylesheet" href="/css/page-elements.css" type="text/css" media="screen" />
<link rel="stylesheet" href="/css/prototip.css" type="text/css" media="screen" />
<link rel="stylesheet" href="/css/jqueryui/custom/jquery-ui.css" type="text/css" media="screen">
<link rel="stylesheet" href="/css/jquery.qtip.css" type="text/css" media="screen">
<!--[if lte IE 6]>
<link rel="stylesheet" href="/css/new/ie6.css" type="text/css" media="screen" />
<![endif]-->
<!--[if IE 7]>
<link rel="stylesheet" href="/css/new/ie7.css" type="text/css" media="screen" />
<![endif]-->
<!--[if IE 8]>
<link rel="stylesheet" href="/css/new/ie8.css" type="text/css" media="screen" />
<![endif]-->
<!--<link media="only screen and (max-device-width: 480px), only screen and (min-device-width: 560px) and (max-device-width: 1136px) and (-webkit-min-device-pixel-ratio: 2)"
	href="/css/iphone.css" type="text/css" rel="stylesheet" />-->
<script src="/js/jquery-1.7.1.min.js"></script>
<script src="/js/jquery-ui-1.8.11.min.js"></script>
<script src="/js/functions.js"></script>
<script src="/js/jquery.qtip.min.js"></script>
<?php
    # Include the below JavaScript, but only if the browser is IE. We do an Opera check because
    # Opera can include the text "MSIE" in its user agent string.
    if (
            strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')
            &&
            !strpos($_SERVER['HTTP_USER_AGENT'], 'Opera')
        )
    {
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
					<div id="date">
						<strong><script>document.write($.datepicker.formatDate('DD, MM dd, yy', new Date()));</script></strong>
					</div>
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
									<li><a href="/legislator/dmadams/">Dawn Adams (D-Richmond)</a></li>
									<li><a href="/legislator/lradams/">Les Adams (R-Chatham)</a></li>
									<li><a href="/legislator/ldaird/">Lashrecse D. Aird (D-Petersburg)</a></li>
									<li><a href="/legislator/dbalbo/">Dave Albo (R-Springfield)</a></li>
									<li><a href="/legislator/tlaustin/">Terry Austin (R-Buchanan)</a></li>
									<li><a href="/legislator/hsayala/">Hala Ayala (D-Woodbridge)</a></li>
									<li><a href="/legislator/lbagby/">Lamont Bagby (D-Richmond)</a></li>
									<li><a href="/legislator/rpbell/">Dickie Bell (R-Staunton)</a></li>
									<li><a href="/legislator/jjbell/">John Bell (D-Chantilly)</a></li>
									<li><a href="/legislator/rbbell/">Rob Bell (R-Charlottesville)</a></li>
									<li><a href="/legislator/rtbloxom/">Rob Bloxom (R-Accomack)</a></li>
									<li><a href="/legislator/jmbourne/">Jeff Bourne (D-Richmond)</a></li>
									<li><a href="/legislator/jbboysko/">Jennifer Boysko (D-Herndon)</a></li>
									<li><a href="/legislator/embrewer/">Emily Brewer (R-Suffolk)</a></li>
									<li><a href="/legislator/dlbulova/">David Bulova (D-Fairfax)</a></li>
									<li><a href="/legislator/kjbyron/">Kathy Byron (R-Lynchburg)</a></li>
									<li><a href="/legislator/jlcampbell/">Jeff Campbell (R-Marion)</a></li>
									<li><a href="/legislator/bbcarr/">Betsy Carr (D-Richmond)</a></li>
									<li><a href="/legislator/jcarrollfoy/">Jennifer Carroll Foy (D-Woodbridge)</a></li>
									<li><a href="/legislator/ljcarter/">Lee Carter (D-Manassas)</a></li>
									<li><a href="/legislator/blcline/">Ben Cline (R-Amherst)</a></li>
									<li><a href="/legislator/mlcole/">Mark Cole (R-Fredericksburg)</a></li>
									<li><a href="/legislator/cecollins/">Chris Collins (R-Winchester)</a></li>
									<li><a href="/legislator/mkcox/">Kirk Cox (R-Colonial Heights)</a></li>
								</ul>
							</li>
							<li>D-H »
								<ul class="legislators">
									<li><a href="/legislator/grdavis/">Glenn Davis (R-Virginia Beach)</a></li>
									<li><a href="/legislator/kkdelaney/">Karrie Delaney (D-Centreville)</a></li>
									<li><a href="/legislator/jeedmunds/">James Edmunds (R-South Boston)</a></li>
									<li><a href="/legislator/cmfariss/">Matt Fariss (R-Rustburg)</a></li>
									<li><a href="/legislator/erfiller-corn/">Eileen Filler-Corn (D-Fairfax Station)</a></li>
									<li><a href="/legislator/hffowler/">Buddy Fowler (R-Ashland)</a></li>
									<li><a href="/legislator/kkfowler/">Kelly Fowler (D-Virginia Beach</a></li>
									<li><a href="/legislator/njfreitas/">Nick Freitas (R-Culpeper)</a></li>
									<li><a href="/legislator/tsgarrett/">Scott Garrett (R-Lynchburg)</a></li>
									<li><a href="/legislator/ctgilbert/">Todd Gilbert (R-Woodstock)</a></li>
									<li><a href="/legislator/gwgooditis/">Wendy Gooditis (D-Clarke)</a></li>
									<li><a href="/legislator/erguzman/">Elizabeth Guzman (D-Dale City)</a></li>
									<li><a href="/legislator/gdhabeeb/">Greg Habeeb (R-Salem)</a></li>
									<li><a href="/legislator/cehayes/">Cliff Hayes (D-Chesapeake)</a></li>
									<li><a href="/legislator/cthead/">Chris Head (R-Roanoke)</a></li>
									<li><a href="/legislator/gchelsel/">Gordon Helsel (R-Poquoson)</a></li>
									<li><a href="/legislator/seheretick/">Steve Heretick (D-Portsmouth)</a></li>
									<li><a href="/legislator/clherring/">Charniele Herring (D-Alexandria)</a></li>
									<li><a href="/legislator/mkhodges/">Keith Hodges (R-Urbanna)</a></li>
									<li><a href="/legislator/pahope/">Patrick Hope (D-Arlington)</a></li>
									<li><a href="/legislator/tdhugo/">Tim Hugo (R-Centreville)</a></li>
									<li><a href="/legislator/clhurst/">Chris Hurst (D-Blacksburg)</a></li>
								</ul>
							</li>
							<li>I-L »
								<ul class="legislators">
									<li><a href="/legislator/reingram/">Riley Ingram (R-Hopewell)</a></li>
									<li><a href="/legislator/mjames/">Matthew James (D-Portsmouth)</a></li>
									<li><a href="/legislator/scjones/">Chris Jones (R-Suffolk)</a></li>
									<li><a href="/legislator/jcjones/">Jay Jones (D-Norfolk)</a></li>
									<li><a href="/legislator/mlkeam/">Mark Keam (D-Vienna)</a></li>
									<li><a href="/legislator/tgkilgore/">Terry Kilgore (R-Gate City)</a></li>
									<li><a href="/legislator/bdknight/">Barry Knight (R-Virginia Beach)</a></li>
									<li><a href="/legislator/lkkory/">Kaye Kory (D-Falls Church)</a></li>
									<li><a href="/legislator/pekrizek/">Paul Krizek (D-Alexandria)</a></li>
									<li><a href="/legislator/rslandes/">Steve Landes (R-Weyers Cave)</a></li>
									<li><a href="/legislator/dalarock/">Dave LaRock (R-Loudoun)</a></li>
									<li><a href="/legislator/jaleftwich/">Jay Leftwich (R-Chesapeake)</a></li>
									<li><a href="/legislator/mhlevine/">Mark Levine (D-Alexandria)</a></li>
									<li><a href="/legislator/jclindsey/">Joe Lindsey (D-Norfolk)</a></li>
									<li><a href="/legislator/ahlopez/">Alfonso Lopez (D-Arlington)</a></li>
								</ul>
							</li>
							<li>M-R »
								<ul class="legislators">
									<li><a href="/legislator/dwmarshall/">Danny Marshall (R-Danville)</a></li>
									<li><a href="/legislator/jjmcguire/">John McGuire (R-Glen Allen)</a></li>
									<li><a href="/legislator/dlmcquinn/">Delores McQuinn (D-Richmond)</a></li>
									<li><a href="/legislator/jsmiyares/">Jason Miyares (R-Virginia Beach)</a></li>
									<li><a href="/legislator/jwmorefield/">Will Morefield (R-North Tazewell)</a></li>
									<li><a href="/legislator/mpmullin/">Mike Mullin (D-Newport News)</a></li>
									<li><a href="/legislator/kjmurphy/">Kathleen Murphy (D-McLean)</a></li>
									<li><a href="/legislator/idoquinn/">Israel O'Quinn (R-Bristol)</a></li>
									<li><a href="/legislator/rdorrock/">Bobby Orrock (R-Thornburg)</a></li>
									<li><a href="/legislator/ckpeace/">Chris Peace (R-Mechanicsville)</a></li>
									<li><a href="/legislator/tepillion/">Todd E. Pillion (R-Abingdon)</a></li>
									<li><a href="/legislator/krplum/">Ken Plum (D-Reston)</a></li>
									<li><a href="/legislator/blpogge/">Brenda Pogge (R-Williamsburg)</a></li>
									<li><a href="/legislator/cdpoindexter/">Charles Poindexter (R-Glade Hill)</a></li>
									<li><a href="/legislator/msprice/">Marcia Price (D-Newport News)</a></li>
									<li><a href="/legislator/mbransone/">Margaret Ransone (R-Kinsale)</a></li>
									<li><a href="/legislator/srasoul/">Sam Rasoul (D-Roanoke)</a></li>
									<li><a href="/legislator/dareid/">David Reid (D-Loudoun)</a></li>
									<li><a href="/legislator/rlrobinson/">Roxann Robinson (R-Chesterfield)</a></li>
									<li><a href="/legislator/dhrodman/">Debra Rodman (R-Henrico)</a></li>
									<li><a href="/legislator/daroem/">Danica Roem (D-Manassas Park)</a></li>
									<li><a href="/legislator/lnrush/">Nick Rush (R-Christiansburg)</a></li>

								</ul>
							</li>
							<li>S-Z »
								<ul class="legislators">
									<li><a href="/legislator/mdsickles/">Mark Sickles (D-Alexandria)</a></li>
									<li><a href="/legislator/mbsimon/">Marcus Simon (D-Falls Church)</a></li>
									<li><a href="/legislator/cpstolle/">Chris Stolle (R-Virginia Beach)</a></li>
									<li><a href="/legislator/rcsullivan/">Rip Sullivan (D-Arlington)</a></li>
									<li><a href="/legislator/rmthomas/">Bob Thomas (R-Stafford)</a></li>
									<li><a href="/legislator/letorian/">Luke Torian (D-Woodbridge)</a></li>
									<li><a href="/legislator/djtoscano/">David Toscano (D-Charlottesville)</a></li>
									<li><a href="/legislator/kkltran/">Kathy Tran (D-Springfield)</a></li>
									<li><a href="/legislator/cbturpin/">Cheryl Turpin (D-Virginia Beach)</a></li>
									<li><a href="/legislator/rctyler/">Roslyn Tyler (D-Jarratt)</a></li>
									<li><a href="/legislator/stvanvalkenburg/">Schuyler VanValkenburg (D-Henrico)</a></li>
									<li><a href="/legislator/jaward/">Jeion Ward (D-Hampton)</a></li>
									<li><a href="/legislator/rlware/">Lee Ware (R-Powhatan)</a></li>
									<li><a href="/legislator/vewatts/">Vivian Watts (D-Annandale)</a></li>
									<li><a href="/legislator/mjwebert/">Michael Webert (R-Marshall)</a></li>
									<li><a href="/legislator/aowilt/">Tony Wilt (R-Harrisonburg)</a></li>
									<li><a href="/legislator/tcwright/">Tommy Wright (R-Victoria)</a></li>
									<li><a href="/legislator/deyancey/">David Yancey (R-Newport News)</a></li>
								</ul>
							</li>
							</ul>
						</li>
						<li>Senate »
							<ul class="alphabetic">
								<li>A-M »
									<ul class="legislators">
										<li><a href="/legislator/glbarker/">George Barker (D-Alexandria)</a></li>
										<li><a href="/legislator/rhblack/">Dick Black (R-Leesburg)</a></li>
										<li><a href="/legislator/cwcarrico/">Bill Carrico (R-Grayson)</a></li>
										<li><a href="/legislator/abchafin/">Ben Chafin (R-Lebanon)</a></li>
										<li><a href="/legislator/afchase/">Amanda Chase (R-Midlothian)</a></li>
										<li><a href="/legislator/jacosgrove/">John Cosgrove (R-Chesapeake)</a></li>
										<li><a href="/legislator/rrdance/">Roz Dance (D-Petersburg)</a></li>
										<li><a href="/legislator/rcdeeds/">Creigh Deeds (D-Bath)</a></li>
										<li><a href="/legislator/wrdesteph/">Bill DeSteph (R-Virginia Beach)</a></li>
										<li><a href="/legislator/ssdunnavant/">Siobhan Dunnavant (R-Henrico)</a></li>
										<li><a href="/legislator/apebbin/">Adam Ebbin (D-Alexandria)</a></li>
										<li><a href="/legislator/jsedwards/">John Edwards (D-Roanoke)</a></li>
										<li><a href="/legislator/bafavola/">Barbara Favola (D-Arlington)</a></li>
										<li><a href="/legislator/ewhanger/">Emmett Hanger (R-Mount Solon)</a></li>
										<li><a href="/legislator/jdhowell/">Janet Howell (D-Reston)</a></li>
										<li><a href="/legislator/lwlewis/">Lynwood Lewis (D-Accomac)</a></li>
										<li><a href="/legislator/melocke/">Mamie Locke (D-Hampton)</a></li>
										<li><a href="/legislator/lllucas/">Louise Lucas (D-Portsmouth)</a></li>
										<li><a href="/legislator/dwmarsden/">Dave Marsden (D-Burke)</a></li>
										<li><a href="/legislator/tmmason/">Monty Mason (D-Williamsburg)</a></li>
										<li><a href="/legislator/jlmcclellan/">Jennifer McClellan (D-Richmond)</a></li>
										<li><a href="/legislator/rtmcdougle/">Ryan McDougle (R-Mechanicsville)</a></li>
										<li><a href="/legislator/jsmcpike/">Jeremy McPike (D-Dale City)</a></li>
									</ul>
								</li>
								<li>N-Z »
									<ul class="legislators">
										<li><a href="/legislator/sdnewman/">Steve Newman (R-Forest)</a></li>
										<li><a href="/legislator/tknorment/">Tommy Norment (R-Williamsburg)</a></li>
										<li><a href="/legislator/mdobenshain/">Mark Obenshain (R-Harrisonburg)</a></li>
										<li><a href="/legislator/mjpeake/">Mark Peake (R-Lynchburg)</a></li>
										<li><a href="/legislator/jcpetersen/">Chap Petersen (D-Fairfax)</a></li>
										<li><a href="/legislator/bereeves/">Bryce Reeves (R-Spotsylvania)</a></li>
										<li><a href="/legislator/fmruff/">Frank Ruff (R-Clarksville)</a></li>
										<li><a href="/legislator/rlsaslaw/">Dick Saslaw (D-Springfield)</a></li>
										<li><a href="/legislator/lspruill/">Lionell Spruill (D-Chesapeake)</a></li>
										<li><a href="/legislator/wmstanley/">Bill Stanley (R-Moneta)</a></li>
										<li><a href="/legislator/rhstuart/">Richard Stuart (R-Westmoreland)</a></li>
										<li><a href="/legislator/ghsturtevant/">Glen Sturtevant (R-Midlothian)</a></li>
										<li><a href="/legislator/drsuetterlein/">David Suetterlein (R-Salem)</a></li>
										<li><a href="/legislator/sasurovell/">Scott Surovell (D-Mount Vernon)</a></li>
										<li><a href="/legislator/jhvogel/">Jill Holtzman Vogel (R-Winchester)</a></li>
										<li><a href="/legislator/fwwagner/">Frank Wagner (R-Virginia Beach)</a></li>
										<li><a href="/legislator/jtwexton/">Jennifer Wexton (D-Leesburg)</a></li>
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
					<li id="t-blog"><a href="/blog/" accesskey="b">Blog</a></li>
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
		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', 'UA-76084-4']);
		_gaq.push(['_trackPageview']);

		(function() {
			var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
			ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		})();
	</script>
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

		$(document).ready(function() {
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
						text: 'Loading .&thinsp;.&thinsp;.',
						ajax: {
							url: 'https://api.richmondsunlight.com/1.0/bill/'+year+'/'+bill_number+'.json',
							type: 'GET',
							dataType: 'jsonp',
							success: function(data, status) {
								var content = '<a href="/legislator/' + data.patron.id + '/">' + data.patron.name + '</a>: ' + data.summary.truncate();
								this.set('content.text', content);
							}
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
						text: 'Loading .&thinsp;.&thinsp;.',
						ajax: {
							url: 'https://api.richmondsunlight.com/1.1/legislator/'+legislator+'.json',
							type: 'GET',
							dataType: 'jsonp',
							success: function(data, status) {
								var d = new Date(Date.parse(data.date_started));
								var content = '<img src="/images/legislators/thumbnails/' + legislator + '.jpg" height="50" style="float: left; margin: 0 .5em .5em 0" \/>'
									+ '<strong>' + data.name_formatted + '</strong></br >Represents: '
									+ data.district_description + '<br />Took Office: ' + data.date_started.substring(0,4);
								this.set('content.text', content);
							}
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
						text: 'Loading .&thinsp;.&thinsp;.',
						ajax: {
							url: 'https://vacode.org/api/law/'+section_number+'/',
							type: 'GET',
							data: { fields: 'catch_line,ancestry', key: 'zxo8k592ztiwbgre' },
							dataType: 'jsonp',
							success: function(section, status) {
								if( section.ancestry instanceof Object ) {
									var content = '';
									for (key in section.ancestry) {
										var content = section.ancestry[key].name + ' → ' + content;
									}
								}
								var content = content + section.catch_line;
								this.set('content.text', content);
							}
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
						text: 'Loading .&thinsp;.&thinsp;.',
						ajax: {
							url: 'https://vacode.org/api/dictionary/' + encodeURI(term) + '/',
							type: 'GET',
							data: { section: section_number, key: 'zxo8k592ztiwbgre' },
							dataType: 'jsonp',
							success: function(data, status) {
								var content = data.definition;
								if (data.section_number != null) {
									content = content + ' (<a href="' + data.url + '">§&nbsp;' + data.section_number + '</a>)';
								}
								else if (data.source) {
									content = content + ' (Source: <a href="' + data.url + '">' + data.source + '</a>)';
								}
								this.set('content.text', content);
							}
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
	</script>

</body>
</html>
