<?php

###
# RSS: Dashboard, by Portfolio
#
# PURPOSE
# Lists the last 20 bill actions of bills for a particular portfolio.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/photosynthesis.inc.php';

# LOCALIZE VARIABLES
$hash = urldecode($_REQUEST['hash'] ?? '');
if (empty($hash)) {
    die('No hash found.');
}

# The hash becomes part of a cache file's name, so allow only what the URL rewrite rule does.
if (!preg_match('/^[a-z0-9]{5}$/', $hash)) {
    http_response_code(404);
    exit();
}

# PAGE CONTENT

# Check to see if there's any need to regenerate this RSS feed -- only do so if it's more than
# three minutes old.
if ((file_exists(CACHE_DIR . '/portfolio-' . $hash . '.xml')) && ((filemtime(CACHE_DIR . '/portfolio-' . $hash . '.xml') + 180) > time())) {
    header('Content-Type: application/rss+xml');
    header('Last-Modified: ' . date('r', filemtime(CACHE_DIR . '/portfolio-' . $hash . '.xml')));
    header('ETag: ' . md5_file(CACHE_DIR . '/portfolio-' . $hash . '.xml'));
    readfile(CACHE_DIR . '/portfolio-' . $hash . '.xml');
    exit();
}

# Open a database connection.
$database = new Database();
$database->connect_mysqli();

# Query the database for information on this portfolio.
$sql = 'SELECT id, name
		FROM dashboard_portfolios
		WHERE hash = "' . mysqli_real_escape_string($GLOBALS['db'], $hash) . '"';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) == 0) {
    die();
}
$portfolio = mysqli_fetch_array($result);
$portfolio = array_map('stripslashes_nullsafe', $portfolio);

# Query the database for all bills in that portfolio.
$sql = 'SELECT bills.number, bills.catch_line, bills.summary, bills.date_modified,
			(SELECT status
			FROM bills_status
			WHERE bills.id=bills_status.bill_id
			ORDER BY bills_status.date DESC, bills_status.id DESC
			LIMIT 1) AS status
		FROM bills
		LEFT JOIN dashboard_bills
			ON bills.id=dashboard_bills.bill_id
		LEFT JOIN dashboard_portfolios
			ON dashboard_bills.portfolio_id = dashboard_portfolios.id
		WHERE dashboard_portfolios.id="' . $portfolio['id'] . '"
		AND bills.session_id=' . SESSION_ID;
$result = mysqli_query($GLOBALS['db'], $sql);

# Don't check to make sure the query was successful -- we want to make sure that people can
# even subscribe to feeds for tags that have introduced nothing yet.

$rss_content = '';
$latest = null;

# Generate the RSS.
while ($bill = mysqli_fetch_array($result)) {
    $date = Rss::timestamp($bill['date_modified']);
    if ($date !== null) {
        $latest = max($latest ?? 0, $date);
    }
    $rss_content .= Rss::item(
        $bill['catch_line'] . ' (' . strtoupper($bill['number']) . ')',
        'https://www.richmondsunlight.com/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/',
        '<p>' . $bill['summary'] . '</p><p><strong>Status: '
            . htmlspecialchars((string) $bill['status'], ENT_QUOTES, 'UTF-8') . '</strong></p>',
        null,
        $date
    );
}

$rss = Rss::feed(
    $portfolio['name'],
    'https://www.richmondsunlight.com/photosynthesis/' . $hash . '/',
    'https://www.richmondsunlight.com/photosynthesis/rss/portfolio/' . $hash . '/',
    'Bills you’re tracking in the “' . $portfolio['name'] . '” portfolio.',
    $rss_content,
    $latest
);

# Cache the RSS file.
send_rss_and_cache(CACHE_DIR . '/portfolio-' . $hash . '.xml', $rss, 'application/rss+xml');
