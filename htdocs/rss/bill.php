<?php

###
# Bills' Activity by Individual Bill
#
# PURPOSE
# Lists the last 20 bill actions for a specific bill.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';

# LOCALIZE VARIABLES
$bill['number'] = strtolower($_REQUEST['number'] ?? '');

# The number becomes part of a cache file's name, so allow only what the URL rewrite rule does.
if (!preg_match('/^[a-z0-9-]{1,10}$/', $bill['number'])) {
    http_response_code(404);
    exit();
}

# PAGE CONTENT

# Check to see if there's any need to regenerate this RSS feed -- only do so
# if it's more than a half hour old.
if (
        (file_exists(CACHE_DIR . '/bill-' . SESSION_YEAR . '-' . $bill['number'] . '.xml'))
        &&
        ((filemtime(CACHE_DIR . '/bill-' . SESSION_YEAR . '-' . $bill['number'] . '.xml') + 1800) > time())
) {
    header('Content-Type: application/rss+xml');
    header('Last-Modified: ' . date('r', filemtime(CACHE_DIR . '/bill-' . SESSION_YEAR . '-'
        . $bill['number'] . '.xml')));
    header('ETag: ' . md5_file(CACHE_DIR . '/bill-' . SESSION_YEAR . '-' . $bill['number'] . '.xml'));
    readfile(CACHE_DIR . '/bill-' . SESSION_YEAR . '-' . $bill['number'] . '.xml');
    exit();
}

# Open a database connection.
$database = new Database();
$database->connect_mysqli();

# Query the database for all bills by that bill number.
$sql = 'SELECT
            bills_status.id,
            bills_status.status,
            bills_status.date,
            bills.catch_line
        FROM bills_status
        LEFT JOIN bills
            ON bills_status.bill_id=bills.id
        WHERE
            bills.id=bills_status.bill_id AND
            bills.session_id = ' . SESSION_ID . ' AND
            bills.number="' . mysqli_real_escape_string($GLOBALS['db'], $bill['number']) . '"
        ORDER BY
            bills_status.date DESC,
            bills_status.id DESC';

$result = mysqli_query($GLOBALS['db'], $sql);

$link = 'https://www.richmondsunlight.com/bill/' . SESSION_YEAR . '/' . $bill['number'] . '/';
$rss_content = '';
$latest = null;

# Generate the RSS.
while ($status = mysqli_fetch_array($result)) {
    $status = array_map('stripslashes_nullsafe', $status);
    $date = Rss::timestamp($status['date']);
    if ($date !== null) {
        $latest = max($latest ?? 0, $date);
    }
    $rss_content .= Rss::item(
        $status['status'],
        $link,
        htmlspecialchars($status['status'], ENT_QUOTES, 'UTF-8'),
        'richmondsunlight-bill-status-' . $status['id'],
        $date
    );
}

$rss = Rss::feed(
    strtoupper($bill['number']) . ' Status',
    $link,
    'https://www.richmondsunlight.com/rss/bill/' . $bill['number'] . '/',
    'The activity of bill ' . strtoupper($bill['number']) . ' in the ' . SESSION_YEAR
        . ' Virginia General Assembly session.',
    $rss_content,
    $latest
);

# Cache the RSS file.
send_rss_and_cache(CACHE_DIR . '/bill-' . SESSION_YEAR . '-' . $bill['number'] . '.xml', $rss);
