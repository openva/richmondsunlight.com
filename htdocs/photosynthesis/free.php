<?php

    ###
    # About Photosynthesis Free
    #
    # PURPOSE
    # Provides a pitch to sign up for Photosynthesis (free version).
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once '../includes/settings.inc.php';
    include_once '../includes/functions.inc.php';

    # PAGE METADATA
    $page_title = 'Photosynthesis';
    $site_section = 'photosynthesis';

    # PAGE CONTENT
    # What Photosynthesis does, for everybody with an account. (It once had paid features; now
    # everybody has all of them. See docs/portfolios.md.)
    $page_body = <<<EOD
		<p><em>Keep lists of the bills that matter to you, free.</em></p>

		<h3>What you can do</h3>
		<ul style="list-style-type: disc; margin-left: 1.5em; margin-bottom: 1em;">

			<li><strong>Portfolios.</strong> Keep as many lists of bills as you want, adding bills
			from any bill’s page, or by number.</li>

			<li><strong>Smart portfolios.</strong> Describe the bills you’re interested
			in—by tag, by patron, or by committee—and they’re gathered for you, and
			kept up to date as bills are filed, tagged, and referred.</li>

			<li><strong>Notes.</strong> Write notes on each bill you’re tracking: whether it
			should pass, and why.</li>

			<li><strong>Public portfolios.</strong> Keep a portfolio to yourself, or share it: it gets
			its own page, and your notes on its bills are shown on those bills’ pages.
			Organizations’ public portfolios are <a href="/photosynthesis/portfolios/">listed
			here</a>.</li>

			<li><strong>RSS feeds.</strong> Every portfolio has its own feed, so you, your coworkers,
			or your members can follow it anywhere.</li>

		</ul>

		<p><a href="/photosynthesis/">Get started</a>: all you need is a Richmond Sunlight
		account.</p>
EOD;

    $page_sidebar = '';

// OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'browser_title',
    'html_head',
    'body_tag',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
