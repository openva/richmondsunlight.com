<?php

###
# About
# 
# PURPOSE
# About the General Assembly.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('includes/functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# Grab the user data.
if (logged_in() === TRUE)
{
	$user = get_user();
}

# PAGE METADATA
$page_title = 'About the General Assembly';
$site_section = 'about';	

# PAGE CONTENT
$page_sidebar = '
	<div class="box">
		<h3>Authorship</h3>
		<p>This description is from Wikipedia, reproduced under the GNU Free Documentation
		License. For the most current version of this, see
		<a href="http://en.wikipedia.org/wiki/Virginia_General_Assembly">the Virginia General
		Assembly entry</a> on Wikipedia.</p>
	</div>';
$page_body = <<<EOD

	<p>The Virginia General Assembly is the legislative body of the Commonwealth of Virginia.
	The General Assembly is a bicameral body consisting of a lower house, the Virginia House of
	Delegates, with 100 members, and an upper house, the Senate of Virginia, with 40 members.
	Combined together, the General Assembly consists of 140 elected representatives from an
	equal amount of constituent districts across the commonwealth. The House of Delegates is
	presided over by a Speaker of the House, while the Senate is presided over by the Lieutenant
	Governor of Virginia. The House and Senate each elect a clerk and sergeant-at-arms. Unlike
	the United States Senate, the Senate of Virginia’s clerk is known as the “Clerk of the
	Senate,” instead of the title “Secretary of the Senate” used in the federal U.S. Senate.</p>

	<p>The legislature meets in Virginia’s capital, Richmond. When sitting in Richmond, the
	General Assembly holds sessions in the Virginia State Capitol, designed by Thomas Jefferson
	in 1788 and expanded in 1904. During the American Civil War, the building was used as the
	capitol of the Confederate States of America, housing the Congress of the Confederate
	States. The building was renovated between 2005 and 2006. Senators and Delegates have their
	offices in the General Assembly Building across the street directly north of the Capitol.
	The Governor of Virginia lives across the street directly east of the Capitol in the
	Virginia Governor’s Mansion.</p>

	<p>The Virginia General Assembly is the oldest legislative body in the Western Hemisphere.
	Its existence dates from the establishment of the House of Burgesses at Jamestown in 1619.
	It previously met in Jamestown, Virginia from 1619 until 1699, when it moved to
	Williamsburg, Virginia and met in the colonial Capitol. It became the General Assembly in
	1776 with the ratification of the Virginia Constitution. The government was moved to
	Richmond in 1780 during the administration of Governor Thomas Jefferson.</p>

	<h2>Senate</h2>
	<p>The Senate of Virginia is the upper house of the Virginia General Assembly. It is
	composed of 40 Senators and is presided over by the Lieutenant Governor of Virginia. Prior
	to Independence, the other part of government was represented by the Governor’s Council, an
	upper house made up of executive counselors appointed by the Governor as advisers.</p>

	<p>The Lieutenant Governor presides daily over the Virginia Senate. In the Lieutenant
	Governor’s absence, a president pro tempore presides, usually a powerful member of the
	majority party. The Senate is equal with the House of Delegates, the lower chamber of the
	legislature, except that taxation bills must originate in the House, just like in the U.S.
	Congress.</p>

	<p>Virginia Senators are elected every four years by the voters of the several senatorial
	districts on the Tuesday succeeding the first Monday in November. The last election took
	place on November 6, 2007.</p>

	<p>In the 2007 election, the Democratic Party reclaimed the majority in the Senate for the
	first time since 1999, when the Republican Party took control of the Senate for the first
	time in history.</p>
	
	<h3>Salary and qualifiations</h3>
	<p>The annual salary for senators is $18,000 per year. To qualify for office, senators must
	be at least 21 years of age at the time of the election, residents of the district they
	represent, and qualified to vote for General Assembly legislators. The regular session of
	the General Assembly is 60 days long during even numbered years and 30 days long during odd
	numbered years, unless extended by a two-thirds vote of both houses.</p>
	
	<h2>House</h2>
	<p>The Virginia House of Delegates is the lower house of the Virginia General Assembly. It
	has 100 members elected for terms of two years; unlike most states, these elections take
	place during odd-numbered years. The House is presided over by the Speaker of the House, who
	is elected from among the House membership by the Delegates. The Speaker is almost always a
	member of the majority party and, as Speaker, becomes the most powerful member of the House.
	The House shares legislative power with the Senate of Virginia, the upper house of the
	Virginia General Assembly. The House of Delegates is the modern-day successor to the
	Virginia House of Burgesses, which first met at Jamestown in 1619. The House is divided into
	Democratic and Republican caucuses. In addition to the Speaker, there is a majority leader,
	majority caucus chair, minority leader, minority caucus chair, and the chairs of the several
	committees of the House. Through the House of Burgesses, the Virginia House of Delegates is
	considered the oldest continuous legislative body in the New World.</p>

	<p>The House has met in Virginia’s Capitol Building, designed by Thomas Jefferson, since
	1788. In recent years, the General Assembly members and staff operate from offices in the
	General Assembly Building, located in Capitol Square.</p>

	<p>Republicans took control of the traditionally Democratic House of Delegates for the first
	time since Reconstruction in 1999 (with the exception of a brief 2 year period in which the
	Readjuster Party was in the majority in the 1880s). However, the Democrats began making a
	comeback under the leadership of Governors Mark Warner and Tim Kaine, gaining six seats
	during Warner’s term in office (2002–2006), and one in a special election at the beginning
	of Kaine’s term.</p>
	
	<h3>Salary and qualifications</h3>
	<p>The annual salary for delegates is $17,640 per year. Each delegate represents roughly
	71,000 people. Candidates for office must be at least 21 years of age at the time of the
	election, residents of the districts they seek to represent, and qualified to vote for
	General Assembly legislators. The regular session of the General Assembly is 60 days long
	during even numbered years and 30 days long during odd numbered years, unless extended by a
	two-thirds vote of both houses.</p>
EOD;

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
