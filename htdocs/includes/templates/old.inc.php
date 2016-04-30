<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html;charset=iso-8859-1" />
	<link rel="stylesheet" href="/css/old.css" type="text/css" />
<?php
if (stristr($_SERVER['HTTP_USER_AGENT'], 'iPhone')) echo '<link media="only screen and (max-device-width: 480px)" href="/css/iphone.css" type="text/css" rel="stylesheet">
<meta name="viewport" content="width=780;">';
?>
	<title>%browser_title%</title>
	<script src="http://www.google-analytics.com/urchin.js" type="text/javascript"></script>
	<script type="text/javascript">
		_uacct = "UA-76084-4";
		urchinTracker();
	</script>
	<script src="/js/functions.js" type="text/javascript"></script>
	<script src="http://www.google.com/jsapi"></script>
	<script>
		// Load Prototype
		google.load("prototype", "1.6.0.3");
		google.load("scriptaculous", "1.8.1");
	</script>
	<script src="/mint/?js" type="text/javascript"></script>
	%html_head%
</head>
<body%body_tag%>
	<div class="content">
		<div class="header">
			<div class="top_info">
				<div class="top_info_right">
					<p>
					<a href="http://www.virginiainterfaithcenter.org/"><img src="/images/vic-68.gif" style="float: right; margin: -5px 10px 5px 5px;" /></a>
					<b><?php echo date('l, F j, Y'); ?></b><br />
					<!--Check today&rsquo;s <a href="/bills/activity/">bill activity</a> or <a href="/bills/introduced/">new bills</a>.</p>-->
					The General Assembly is not in session.
				</div>		
				<div class="top_info_left">
					<p>Tracking Virginia&rsquo;s General Assembly<br />
					since 2007.</p>
				</div>
			</div>
			<div class="logo">
				<h1><a href="/" title="Richmond Sunlight"><span class="dark">Richmond</span> <span class="bright">Sunlight</span></a></h1>
			</div>
		</div>
		
		<div class="bar">
			<ul>
				<li<?php if (($site_section == 'home') || !isset($site_section)) echo ' class="active"'; ?>><a href="/" accesskey="h" title="The front page of the site"><span style="text-decoration: underline;">H</span>ome</a></li>
				<li<?php if ($site_section == 'bills') echo ' class="active"'; ?>><a href="/bills/" accesskey="b" title="The bills that have been introduced to become law"><span style="text-decoration: underline;">B</span>ills</a></li>
				<li<?php if ($site_section == 'legislators') echo ' class="active"'; ?>><a href="/legislators/" accesskey="r" title="The elected officials that represent us"><span style="text-decoration: underline;">L</span>egislators</a></li>
				<li<?php if ($site_section == 'minutes') echo ' class="active"'; ?>><a href="/minutes/" accesskey="m" title="Text and video of what happens each day"><span style="text-decoration: underline;">M</span>inutes</a></li>
				<li<?php if ($site_section == 'committees') echo ' class="active"'; ?>><a href="/committees/" accesskey="c" title="The groups within the General Assembly"><span style="text-decoration: underline;">C</span>ommittees</a></li>
				<li<?php if ($site_section == 'photosynthesis') echo ' class="active"'; ?>><a href="/photosynthesis/" accesskey="p" title="Personalized bill tracking"><span style="text-decoration: underline;">P</span>hotosynthesis</a></li>
				<li<?php if ($site_section == 'statistics') echo ' class="active"'; ?>><a href="/statistics/" accesskey="s" title="An overview of the session"><span style="text-decoration: underline;">S</span>tatistics</a></li>
				<li<?php if ($site_section == 'blog') echo ' class="active"'; ?>><a href="/blog/" accesskey="o">Bl<span style="text-decoration: underline;">o</span>g</a></li>
			</ul>
			<div id="account">
				%account%
			</div>
		</div>
		<div class="search_field">
			<form method="get" action="/search/">
				<div class="search_form">
					<p>Search 2009 Bills: <input type="text" name="q" class="search" /> <input type="submit" value="Go" class="submit" /></p>
				</div>
			</form>
			
			<form name="legislators" id="legislators">
				<select name="legislator" language="javascript" size="1"
					onchange="window.location = document.legislators.legislator.options[document.legislators.legislator.selectedIndex].value">
					<option>Select a Legislator</option>
					<optgroup label="Delegates">
						<option value="/legislator/wmabbitt">Watkins Abbitt (I-59)</option>
						<option value="/legislator/dbalbo/">Dave Albo (R-42)</option>
						<option value="/legislator/kcalexander/">Kenny Alexander (D-89)</option>
						<option value="/legislator/kjamundson/">Kris Amundson (D-44)</option>
						<option value="/legislator/wlarmstrong/">Ward Armstrong (D-10)</option>
						<option value="/legislator/clathey/">Clay Athey (R-18)</option>
						<option value="/legislator/mebacote/">Mamye BaCote (D-95)</option>
						<option value="/legislator/wkbarlow/">William Barlow (D-64)</option>
						<option value="/legislator/rbbell/">Rob Bell (R-58)</option>
						<option value="/legislator/jfbouchard/">Joe Bouchard (D-83)</option>
						<option value="/legislator/dcbowling/">Danny Bowling (D-3)</option>
						<option value="/legislator/rhbrink/">Bob Brink (D-48)</option>
						<option value="/legislator/dlbulova/">David Bulova (D-37)</option>
						<option value="/legislator/kjbyron/">Kathy Byron (R-22)</option>
						<option value="/legislator/cccaputo/">Chuck Caputo (D-67)</option>
						<option value="/legislator/cwcarrico/">Bill Carrico (R-5)</option>
						<option value="/legislator/blcline/">Ben Cline (R-24)</option>
						<option value="/legislator/mlcole/">Mark Cole (R-88)</option>
						<option value="/legislator/jacosgrove/">John Cosgrove (R-78)</option>
						<option value="/legislator/mkcox/">Kirk Cox (R-66)</option>
						<option value="/legislator/abcrockett-stark/">Crockett-Anne Stark (R-6)</option>
						<option value="/legislator/rrdance/">Rosalyn Dance (D-63)</option>
						<option value="/legislator/apebbin/">Adam Ebbin (D-49)</option>
						<option value="/legislator/aceisenberg/">Al Eisenberg (D-47)</option>
						<option value="/legislator/dlenglin/">David Englin (D-45)</option>
						<option value="/legislator/whfralin/">Bill Fralin (R-17)</option>
						<option value="/legislator/jmfrederick/">Jeff Frederick (R-52)</option>
						<option value="/legislator/tdgear/">Tom Gear (R-91)</option>
						<option value="/legislator/ctgilbert/">Todd Gilbert (R-15)</option>
						<option value="/legislator/hmgriffith/">Morgan Griffith (R-8)</option>
						<option value="/legislator/fphall/">Frank Hall (D-69)</option>
						<option value="/legislator/pahamilton/">Phil Hamilton (R-93)</option>
						<option value="/legislator/fdhargrove/">Frank Hargrove (R-55)</option>
						<option value="/legislator/cnhogan/">Clarke Hogan (R-60)</option>
						<option value="/legislator/athowell/">Algie Howell (D-90)</option>
						<option value="/legislator/wjhowell/">Bill Howell (R-28)</option>
						<option value="/legislator/tdhugo/">Tim Hugo (R-40)</option>
						<option value="/legislator/rdhull/">Bob Hull (D-38)</option>
						<option value="/legislator/sriaquinto/">Sal Iaquinto (R-84)</option>
						<option value="/legislator/reingram/">Riley Ingram (R-62)</option>
						<option value="/legislator/wrjanis/">Bill Janis (R-56)</option>
						<option value="/legislator/jsjoannou/">Johnny Joannou (D-79)</option>
						<option value="/legislator/jpjohnson/">Joseph Johnson (D-4)</option>
						<option value="/legislator/scjones/">Chris Jones (R-76)</option>
						<option value="/legislator/dcjones/">Dwight Jones (D-70)</option>
						<option value="/legislator/tgkilgore/">Terry Kilgore (R-1)</option>
						<option value="/legislator/rslandes/">Steve Landes (R-25)</option>
						<option value="/legislator/lwlewis/">Lynwood Lewis (D-100)</option>
						<option value="/legislator/lslingamfelter/">Scott Lingamfelter (R-31)</option>
						<option value="/legislator/mjlohr/">Matt Lohr (R-26)</option>
						<option value="/legislator/gmloupassi/">Manoil Loupassi (R-68)</option>
						<option value="/legislator/dwmarsden/">Dave Marsden (D-41)</option>
						<option value="/legislator/rgmarshall/">Bob Marshall (R-13)</option>
						<option value="/legislator/dwmarshall/">Danny Marshall (R-14)</option>
						<option value="/legislator/jpmassie/">Jimmie Massie (R-72)</option>
						<option value="/legislator/rwmathieson/">Bobby Mathieson (D-21)</option>
						<option value="/legislator/jtmay/">Joe May (R-33)</option>
						<option value="/legislator/jlmcclellan/">Jennifer McClellan (D-71)</option>
						<option value="/legislator/krmelvin/">Ken Melvin (D-80)</option>
						<option value="/legislator/dwmerricks/">Don Merricks (R-16)</option>
						<option value="/legislator/jhmiller/">Jackson Miller (R-50)</option>
						<option value="/legislator/pjmiller/">Paula Miller (D-87)</option>
						<option value="/legislator/bjmoran/">Brian Moran (D-46)</option>
						<option value="/legislator/hbmorgan/">Harvey Morgan (R-98)</option>
						<option value="/legislator/jdmorrissey/">Joe Morrissey (D-74)</option>
						<option value="/legislator/pfnichols/">Paul Nichols (D-51)</option>
						<option value="/legislator/sanixon/">Sam Nixon (R-27)</option>
						<option value="/legislator/danutter/">Dave Nutter (R-7)</option>
						<option value="/legislator/ggoder/">Glenn Oder (R-94)</option>
						<option value="/legislator/rdorrock/">Bobby Orrock (R-54)</option>
						<option value="/legislator/jmobannon/">John O'Bannon (R-73)</option>
						<option value="/legislator/ckpeace/">Chris Peace (R-97)</option>
						<option value="/legislator/cephillips/">Bud Phillips (D-2)</option>
						<option value="/legislator/krplum/">Ken Plum (D-36)</option>
						<option value="/legislator/blpogge/">Brenda Pogge (R-96)</option>
						<option value="/legislator/cdpoindexter/">Charles Poindexter (R-9)</option>
						<option value="/legislator/depoisson/">David Poisson (D-32)</option>
						<option value="/legislator/acpollard/">Albert Pollard (D-99)</option>
						<option value="/legislator/hrpurkey/">Harry Purkey (R-82)</option>
						<option value="/legislator/leputney/">Lacey Putney (I-19)</option>
						<option value="/legislator/tdrust/">Tom Rust (R-86)</option>
						<option value="/legislator/cbsaxman/">Chris Saxman (R-20)</option>
						<option value="/legislator/etscott/">Ed Scott (R-30)</option>
						<option value="/legislator/jmscott/">Jim Scott (D-53)</option>
						<option value="/legislator/scshannon/">Steve Shannon (D-35)</option>
						<option value="/legislator/bjsherwood/">Beverly Sherwood (R-29)</option>
						<option value="/legislator/jmshuler/">Jim Shuler (D-12)</option>
						<option value="/legislator/mdsickles/">Mark Sickles (D-43)</option>
						<option value="/legislator/lspruill/">Lionell Spruill (D-77)</option>
						<option value="/legislator/tlsuit/">Terrie Suit (R-81)</option>
						<option value="/legislator/rtata/">Robert Tata (R-85)</option>
						<option value="/legislator/djtoscano/">David Toscano (D-57)</option>
						<option value="/legislator/rctyler/">Roslyn Tyler (D-75)</option>
						<option value="/legislator/srvalentine/">Shannon Valentine (D-23)</option>
						<option value="/legislator/mgvanderhye/">Margi Vanderhye (D-34)</option>
						<option value="/legislator/jaward/">Jeion Ward (D-92)</option>
						<option value="/legislator/rlware/">Lee Ware (R-65)</option>
						<option value="/legislator/oware/">Onzlee Ware (D-11)</option>
						<option value="/legislator/vewatts/">Vivian Watts (D-39)</option>
						<option value="/legislator/tcwright/">Tom Wright (R-61)</option>
					</optgroup>
					<optgroup label="Senators">
						<option value="/legislator/glbarker/">George Barker (D-39)</option>
						<option value="/legislator/hbblevins/">Harry Blevins (R-14)</option>
						<option value="/legislator/cjcolgan/">Chuck Colgan (D-29)</option>
						<option value="/legislator/ktcuccinelli/">Ken Cuccinelli (R-37)</option>
						<option value="/legislator/rcdeeds/">Creigh Deeds (D-25)</option>
						<option value="/legislator/jsedwards/">John Edwards (D-21)</option>
						<option value="/legislator/ewhanger/">Emmett Hanger (R-24)</option>
						<option value="/legislator/crhawkins/">Charles Hawkins (R-19)</option>
						<option value="/legislator/mrherring/">Mark Herring (D-33)</option>
						<option value="/legislator/erhouck/">Edd Houck (D-17)</option>
						<option value="/legislator/rhurt/">Robert Hurt (D-17)</option>
						<option value="/legislator/jdhowell/">Janet Howell (D-32)</option>
						<option value="/legislator/melocke/">Mamie Locke (D-2)</option>
						<option value="/legislator/lllucas/">Louise Lucas (D-18)</option>
						<option value="/legislator/hlmarsh/">Henry Marsh (D-16)</option>
						<option value="/legislator/shmartin/">Stephen Martin (R-11)</option>
						<option value="/legislator/rtmcdougle/">Ryan McDougle (R-4)</option>
						<option value="/legislator/admceachin/">Don McEachin (D-9)</option>
						<option value="/legislator/jcmiller/">John Miller (D-1)</option>
						<option value="/legislator/ybmiller/">Yvonne Miller (D-5)</option>
						<option value="/legislator/sdnewman/">Steve Newman (R-23)</option>
						<option value="/legislator/tknorment/">Tommy Norment (R-3)</option>
						<option value="/legislator/rsnortham/">Ralph Northam (D-6)</option>
						<option value="/legislator/mdobenshain/">Mark Obenshain (R-26)</option>
						<option value="/legislator/jcpetersen/">Chap Petersen (D-34)</option>
						<option value="/legislator/pppuckett/">Phil Puckett (D-38)</option>
						<option value="/legislator/ltpuller/">Toddy Puller (D-36)</option>
						<option value="/legislator/fmquayle/">Fred Quayle (R-13)</option>
						<option value="/legislator/wmreynolds/">Roscoe Reynolds (D-20)</option>
						<option value="/legislator/fmruff/">Frank Ruff (R-15)</option>
						<option value="/legislator/rlsaslaw/">Dick Saslaw (D-35)</option>
						<option value="/legislator/rksmith/">Ralph Smith (R-22)</option>
						<option value="/legislator/kwstolle/">Ken Stolle (R-8)</option>
						<option value="/legislator/wastosch/">Walter Stosch (R-12)</option>
						<option value="/legislator/rhstuart/">Richard Stuart (R-28)</option>
						<option value="/legislator/psticer/">Patsy Ticer (D-30)</option>
						<option value="/legislator/jhvogel/">Jill Vogel Holtzman (R-27)</option>
						<option value="/legislator/fwwagner/">Frank Wagner (R-7)</option>
						<option value="/legislator/wcwampler/">William Wampler (R-40)</option>
						<option value="/legislator/jcwatkins/">John Watkins (R-10)</option>
						<option value="/legislator/mmwhipple/">Mary Whipple Margaret (D-31)</option>
					</optgroup>
				</select>
			</form>
		</div>
		
		<div class="left">
			<h1>%page_title%</h1>
		
			%page_body%
		</div>
	
		<div class="right">
			%page_sidebar%
		</div>
	
		<div class="footer">
			<p><a href="/about/site/">About the Site</a> | <a href="/about/rss/">RSS Subscriptions</a> | <a href="/labs/">Labs (API)</a> | <a href="/contact/">Contact Richmond Sunlight</a> | <a href="/about/tos/">Terms of Service</a></p>
			<p>A program of the <a href="http://www.virginiainterfaithcenter.org/">Virginia Interfaith Center</a>.
			Created by
			<a href="http://waldo.jaquith.org/">Waldo Jaquith</a>.  Design based on Luka Cvrk's
			&ldquo;<a href="http://www.oswd.org/design/preview/id/2876">Internet Services</a>.&rdquo;</p>
			<p class="quote">&ldquo;Sunlight is said to be the best of disinfectants.&rdquo; &mdash;Justice Louis Brandeis, 1914</p>
			<a href="http://www.briworks.com/"><img src="/images/bri.gif" id="bri" alt="Hosting donated by Blue Ridge InternetWorks" /></a>
		</div>
	</div>
</body>
</html>