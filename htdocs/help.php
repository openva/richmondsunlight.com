<?php

    ###
    # Help System
    #
    # PURPOSE
    # Display the data that will consistute the "help" system.
    #
    # NOTES
    # This does not use the standard template but, rather, a more stripped-down version.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once 'includes/settings.inc.php';
    include_once 'includes/functions.inc.php';
    include_once 'vendor/autoload.php';

    # LOCALIZE VARIABLES
    $topic = $_REQUEST['topic'];

    # PAGE METADATA
    $page_title = 'Help with ' . ucwords(str_replace('-', ' ', $topic));
    $site_section = '';
    $template = 'help';

    # PAGE CONTENT

    # Use the $topic variable to determine which help information to provide.
    switch ($topic)
    {

        # How to use tags.
        case 'tags':
            $page_body = <<<EOD
				<p>Tags are simple, objective words that describe a bill. The purpose of
				them is to describe bills in a way using plainer, more accessible
				language than the legalese employed by lawmakers.  It makes it easier
				for people to find bills that they're interested in.</p>

				<p>Anybody can add a tag to describe a bill. Tagging bills is a small
				form of community service: you're making it easier for people to know
				what legislation might affect them through that simple, social act.</p>

				<p>Please try to follow these simple guidelines when tagging bills:</p>

				<ul>
					<li><strong>Do</strong> use objective language that describes the
					<em>facts</em> of the bill.</li>
					<li><strong>Don't</strong> use words that describe how <em>you</em>
					feel about the bill.</li>
					<li><strong>Do</strong> use simple, common words that most people
					would be familiar with.</li>
					<li><strong>Don't</strong> go crazy tagging a bill &mdash; most
					bills can be successfully described with no more than a half
					dozen tags.</li>
					<li><strong>Do</strong> pay attention to how others bills are
					tagged and attempt the emulate the standard style.</li>
					<li><strong>Don't</strong> worry about using any uppercase &mdash;
					all tags are automatically converted to lowercase.</li>
				</ul>

				<p>Here are examples of good tags that might appear on a bill
				proposing increasing funding to highways in Fairfax:</p>

				<ul>
					<li>transportation</li>
					<li>highway</li>
					<li>tax</li>
					<li>“fairfax county”</li>
				</ul>

				<p>And here are examples of bad tags about the same bill:</p>

				<ul>
					<li>“tax hike”</li>
					<li>sucks</li>
					<li>“new highway funding”</li>
					<li>monkeys</li>
				</ul>

				<p>In summary: keep it simple, be objective, and try to follow existing
				standards.</p>
EOD;
            break;


        # How to interpret a tag cloud.
        case 'tag-clouds':
            $page_body = <<<EOD
				<p>A “tag cloud” is a visual description of what a group of
				bills tend to be about, with the size of each word indicating how common
				each word is.  The tags are entered on each bill’s page, entered
				by any user of Richmond Sunlight.</p>

				<p>For example, consider this tag cloud for the bills introduced by a
				particular (imaginary) legislator:</p>

				<div style="width: 18em; margin: 1em 0;">
					<span style="font-size: 1em;">tax cut</span>
					<span style="font-size: 1.2em;">sex</span>
					<span style="font-size: 1em;">schools</span>
					<span style="font-size: 1.8em;">mining</span>
					<span style="font-size: 2em;">commendation</span>
					<span style="font-size: 1em;">transportation</span>
					<span style="font-size: 1.3em;">farm</span>
					<span style="font-size: 1.2em;">pregnancy</span>
				</div>

				<p>The largest word is “commendation,” which tells us that this
				legislator introduces more bills commending people than anything else. The
				second largest word is “mining,”, so we know that the second-largest
				number of bills introduced by this legislator pertain to mining. And the
				smallest tags are “tax cut,” “schools,” and
				“transportation,” so we know that the smallest number of bills that
				this legislator introduces are about those three topics.</p>

				<p>It’s just as telling that some words don't show up here at all, meaning
				that the legislator introduced few or no such bills.</p>

				<p>Tag clouds can also be used to summarize the bills introduced by session,
				by party, by committee, or really any other legislative grouping.</p>

EOD;
        break;


        # How to read the status checkboxes.
        case 'status-checkboxes':
            $page_body = <<<EOD
				<p>The bill status checkboxes provide an at-a-glance indication of
				where a bill is in its path between being proposed and becoming law.
				It's very simplified. No bill actually follows such straightforward
				path.</p>

				<ul>
					<li>A green check indicates that the bill passed that step successfully.</li>
					<li>A red X indicates that the bill failed that step.</li>
					<li>A blank box indicates that the bill has not reached that step.</li>
				</ul>
EOD;
        break;


        # How to read the status checkboxes.
        case 'poll':
            $page_body = <<<EOD
				<p>The interactive voting system on every bill’s page (“Do you
				support this bill in its current form?”) is a <em>public</em>
				vote. That is to say that it has nothing at all to do with the actual vote
				at the General Assembly.  It’s in no way scientifically accurate,
				and measures nothing more than the whims of the people who bothered to
				click a little button.</p>
EOD;
        break;


        # How to read the status checkboxes.
        case 'aggregated-poll':
            $page_body = <<<EOD
				<p>This is the collective result of the per-bill polls offered on
				each bill’s page.</p>

				<p>The interactive voting system on every bill’s page (“Do you
				support this bill in its current form?”) is a <em>public</em>
				vote. That is to say that it has nothing at all to do with the actual vote
				at the General Assembly.  It’s in no way scientifically accurate,
				and measures nothing more than the whims of the people who bothered to
				click a little button.</p>

				<p>If a legislator has one bill that’s overwhelmingly opposed,
				and another bill that’s overwhelming supported, that might
				show up on the Aggregated Bill Poll Results as an even number of
				“Yes” and “No” votes.</p>
EOD;


        # How to read the partisanship graph.
        // no break
        case 'partisanship':
            $page_body = <<<EOD
				<p>This is a measure of where a legislator is on the left-to-right political
				spectrum.</p>

				<p>This is calculated by examining the legislator’s copatroning habits, which is to
				say what bills that he signs on to support and who signs on to support <em>his</em>
				bills. First we calculate the percentage of Democrats and Republicans who have
				introduced the bills that he’s cosponsored. Then we calculate the percentage of
				the copatrons of <em>his</em> bills that are Democrats vs. the percentage that are
				Republicans. And, finally, we look at the entire pool of copatrons of all of the
				bills that <em>he's</em> ever copatroned (thousands of such relationships, with any
				legislator who has been in office for at least a few years), and again calculate the
				percentage who are members of each party.</p>

				<p>Those three numbers are averaged, and the resulting rate is displayed
				graphically.</p>
EOD;

    }

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->template = $template;
$page->process();
