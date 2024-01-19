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

    # LOCALIZE VARIABLES
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}
if (isset($_REQUEST['bill'])) {
    $bill = $_REQUEST['bill'];
}

    # Make sure that the year and bill number are valid-looking.
if ((!preg_match('/([0-9]{4})/D', $year)) || (!preg_match('/([b-s]{2})([0-9]+)/D', $year))) {
    unset($bill, $year);
}

    # PAGE CONTENT
    # Open a database connection.
    $database = new Database();
    $database->connect_mysqli();

    # Query the database for the last 20 comments.
    $sql = 'SELECT comments.id, comments.bill_id, comments.date_created AS date,
			comments.name, comments.email, comments.url, comments.comment,
			comments.type, bills.number AS bill_number, sessions.year,
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

    # Generate the RSS.
while ($comment = mysqli_fetch_array($result)) {
    # Aggregate the variables into their RSS components.
    $title = '<![CDATA[' . ($comment['type'] == 'pingback' ? 'Pingback from ' : '') . $comment['name'] . ' ' . $comment['bill_number'] . ']]>';
    $link = 'http://www.richmondsunlight.com/bill/' . $comment['year'] . '/' . $comment['bill_number'] . '/#comment-' . $comment['number'];
    $description = '<![CDATA[
			' . nl2p($comment['comment']) . '
			]]>';

    # Now assemble those RSS components into an XML fragment.
    $rss_content .= '
		<item>
			<title>' . $title . '</title>
			<link>' . $link . '</link>
			<description>' . $description . '</description>
		</item>';

    # Unset those variables for reuse.
    unset($item_completed, $title, $link, $description);
}



    $rss = '<?xml version="1.0" encoding=\'utf-8\'?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://www.rssboard.org/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>Richmond Sunlight Comments</title>
		<link>http://www.richmondsunlight.com/</link>
		<description>The most recent comments posted to bills on Richmond Sunlight.</description>
		<language>en-us</language>
		' . $rss_content . '
	</channel>
</rss>';

    header('Content-Type: application/xml');
    echo $rss;
