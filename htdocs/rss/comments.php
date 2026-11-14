<?php

###
# Comment Activity RSS
#
# PURPOSE
# Lists the last 20 comments posted.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';

# PAGE CONTENT
# Open a database connection.
$database = new Database();
$database->connect_mysqli();

# Query the database for the last 20 comments.
$sql = 'SELECT
            comments.id,
            comments.bill_id,
            comments.date_created AS date,
            comments.name,
            comments.email,
            comments.url,
            comments.comment,
            comments.type,
            bills.number AS bill_number,
            sessions.year,
            (
                SELECT COUNT(*)
                FROM comments
                WHERE bill_id=bills.id AND status="published"
                AND date_created <= date
            ) AS number
        FROM comments
        LEFT JOIN bills
            ON bills.id=comments.bill_id
        LEFT JOIN sessions
            ON bills.session_id=sessions.id
        WHERE comments.status="published"
        ORDER BY comments.date_created DESC
        LIMIT 20';
$result = mysqli_query($GLOBALS['db'], $sql);

$rss_content = '';
$latest = null;

# Generate the RSS.
while ($comment = mysqli_fetch_array($result)) {
    $date = Rss::timestamp($comment['date']);
    if ($date !== null) {
        $latest = max($latest ?? 0, $date);
    }
    $rss_content .= Rss::item(
        ($comment['type'] == 'pingback' ? 'Pingback from ' : '') . $comment['name'] . ' '
            . strtoupper($comment['bill_number']),
        'https://www.richmondsunlight.com/bill/' . $comment['year'] . '/' . $comment['bill_number']
            . '/#comment-' . $comment['number'],
        nl2p($comment['comment']),
        'richmondsunlight-comment-' . $comment['id'],
        $date
    );
}

$rss = Rss::feed(
    'Richmond Sunlight Comments',
    'https://www.richmondsunlight.com/',
    'https://www.richmondsunlight.com/rss/comments/',
    'The most recent comments posted to bills on Richmond Sunlight.',
    $rss_content,
    $latest
);

header('Content-Type: application/rss+xml');
echo $rss;
