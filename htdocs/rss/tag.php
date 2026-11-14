<?php

    ###
    # Bills' Activity by Tag
    #
    # PURPOSE
    # Lists the last 20 bill actions of those patroned by a given legislator.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';

    # LOCALIZE VARIABLES
    $tag = urldecode($_REQUEST['tag'] ?? '');

    # The tag becomes part of a cache file's name, so allow only what the URL rewrite rule does.
if (!preg_match('/^[a-z0-9 +-]{1,100}$/', $tag)) {
    http_response_code(404);
    exit();
}

    # PAGE CONTENT

    # Check to see if there's any need to regenerate this RSS feed -- only do so
    # if it's more than a half hour old.
if ((file_exists(CACHE_DIR . '/tag-' . $tag . '.xml')) && ((filemtime(CACHE_DIR . '/tag-' . $tag . '.xml') + 1800) > time())) {
    header('Content-Type: application/rss+xml');
    header('Last-Modified: ' . date('r', filemtime(CACHE_DIR . '/tag-' . $tag . '.xml')));
    header('ETag: ' . md5_file(CACHE_DIR . '/tag-' . $tag . '.xml'));
    readfile(CACHE_DIR . '/tag-' . $tag . '.xml');
    exit();
}

    # Open a database connection.
    $database = new Database();
    $database->connect_mysqli();

    # Query the database for all bills by that tag.
    $sql = 'SELECT bills.number, bills.catch_line, bills.summary, bills.date_modified,
				(SELECT status
				FROM bills_status
				WHERE bills.id=bills_status.bill_id
				ORDER BY bills_status.date DESC, bills_status.id DESC
				LIMIT 1) AS status
			FROM bills
			LEFT JOIN tags
			ON tags.bill_id=bills.id
			WHERE bills.session_id = ' . SESSION_ID . '
			AND tags.tag="' . mysqli_real_escape_string($GLOBALS['db'], $tag) . '"';
    $result = mysqli_query($GLOBALS['db'], $sql);

    // Don't check to make sure the query was successful -- we want to make sure that people can
    // even subscribe to feeds for tags that have nothing introduced yet.

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
    'Bills Tagged “' . $tag . '”',
    'https://www.richmondsunlight.com/bills/tags/' . $tag . '/',
    'https://www.richmondsunlight.com/rss/tag/' . $tag . '/',
    'The bills filed in the ' . SESSION_YEAR . ' Virginia General Assembly session that have been tagged with “'
        . $tag . '”.',
    $rss_content,
    $latest
);

    # Cache the RSS file.
    send_rss_and_cache(CACHE_DIR . '/tag-' . $tag . '.xml', $rss);
