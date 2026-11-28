<?php

    ###
    # List Photosynthesis Portfolios
    #
    # PURPOSE
    # List all organizational portfolios that contain bills for this session.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once '../includes/settings.inc.php';
    include_once '../includes/functions.inc.php';

    # DECLARATIVE FUNCTIONS
    # Run those functions that are necessary prior to loading this specific
    # page.
    $database = new Database();
    $database->connect_mysqli();

    # INITIALIZE SESSION
    resume_session();

    # PAGE METADATA
    $page_title = 'Photosynthesis Portfolios';
    $site_section = 'photosynthesis';

    # PAGE CONTENT

    $page_body = '<p>The following organizations are tracking legislation for the ' . SESSION_YEAR . '
		session in Photosynthesis. Select the name of any organization to see the bills that they
		are tracking, along with any commentary that they may have provided about each bill.
		(Note that a great many individuals also use Photosynthesis to track legislation, but we
		only list organizations here.)</p>';

    # Every organization's public portfolios with bills of this session, each organization once.
    $sql = 'SELECT dashboard_user_data.organization, dashboard_portfolios.hash, dashboard_portfolios.name
			FROM dashboard_user_data
			JOIN dashboard_portfolios
				ON dashboard_user_data.user_id = dashboard_portfolios.user_id
			WHERE dashboard_user_data.organization IS NOT NULL AND dashboard_user_data.organization != ""
			AND dashboard_portfolios.public = "y"
			AND EXISTS (
				SELECT 1
				FROM dashboard_bills
				JOIN bills ON dashboard_bills.bill_id = bills.id
				JOIN sessions ON bills.session_id = sessions.id
				WHERE dashboard_bills.portfolio_id = dashboard_portfolios.id
				AND dashboard_bills.excluded = "n"
				AND sessions.year = ?)
			ORDER BY dashboard_user_data.organization ASC, dashboard_portfolios.name ASC';
    $result = mysqli_execute_query($GLOBALS['db'], $sql, [SESSION_YEAR]);
    $organizations = [];
    while ($result && ($portfolio = mysqli_fetch_assoc($result))) {
        $portfolio = array_map('stripslashes_nullsafe', $portfolio);
        $organizations[$portfolio['organization']][] = $portfolio;
    }

if (count($organizations) == 0) {
    $page_body .= '<p>No organizations have yet added any bills to Photosynthesis for the current
			General Assembly session.</p>';
} else {
    $page_body .= '<ul class="organizations">';
    foreach ($organizations as $organization => $portfolios) {
        $links = array_map(
            fn($portfolio) => '<a href="/photosynthesis/' . Portfolio::text($portfolio['hash']) . '/">'
                . Portfolio::text($portfolio['name']) . '</a>',
            $portfolios
        );
        $page_body .= '<li><strong>' . Portfolio::text($organization) . '</strong>: ' . implode(', ', $links) . '</li>';
    }
    $page_body .= '</ul>';
}

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
