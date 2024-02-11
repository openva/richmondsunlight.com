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
    session_start();

    # PAGE METADATA
    $page_title = 'Photosynthesis Portfolios';
    $site_section = 'photosynthesis';

    # PAGE CONTENT

    $page_body = '<p>The following organizations are tracking legislation for the ' . SESSION_YEAR . '
		session in Photosynthesis. Select the name of any organization to see the bills that they
		are tracking, along with any commentary that they may have provided about each bill.
		(Note that a great many individuals also use Photosynthesis to track legislation, but we
		only list organizations here.)</p>';

    # Select every public organizational portfolio.
    $sql = 'SELECT DISTINCT dashboard_portfolios.hash, dashboard_user_data.organization
			FROM dashboard_user_data
			LEFT JOIN dashboard_portfolios
				ON dashboard_user_data.user_id = dashboard_portfolios.user_id
			WHERE dashboard_user_data.organization IS NOT NULL AND dashboard_portfolios.public="y"
			AND (
				SELECT COUNT(*)
				FROM dashboard_bills
				LEFT JOIN bills ON dashboard_bills.bill_id = bills.id
				LEFT JOIN sessions ON bills.session_id = sessions.id
				WHERE portfolio_id = dashboard_portfolios.id
				AND sessions.year = ' . SESSION_YEAR . ') > 0
			ORDER BY dashboard_user_data.organization ASC';
    $result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) == 0) {
    $page_body .= '<p>No organizations have yet added any bills to Photosynthesis for the current
			General Assembly session.</p>';
} else {
    $page_body .= '<ul>';
    while ($portfolio = mysqli_fetch_array($result)) {
        $portfolio = array_map('stripslashes', $portfolio);
        $page_body .= '<li><a href="/photosynthesis/' . $portfolio['hash'] . '/">'
            . $portfolio['organization'] . '</a></li>';
    }
    $page_body .= '</ul>';
}

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
