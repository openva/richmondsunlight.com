<?php

    ###
    # Legislators' Activity
    #
    # PURPOSE
    # Lists the last 20 bill actions of those patroned by a given legislator.
    #
    # TODO
    # * Have die() provide an error that will appear in an RSS reader.
    # * Support If-Modified-Since and If-None-Match headers to reduce bandwidth.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';

    # LOCALIZE VARIABLES
    $legislator['shortname'] = $_REQUEST['shortname'];

    # PAGE CONTENT

    # Check to see if there's any need to regenerate this RSS feed -- only do so if it's more than
    # a half hour old.
    if (
        (file_exists('cache/' . $legislator['shortname'] . '.xml'))
        &&
        (
            (filemtime('cache/' . $legislator['shortname'] . '.xml') + 1800) > time()
        )
        ) {
        header('Content-Type: application/rss+xml');
        header('Last-Modified: ' . date('r', filemtime('cache/' . $legislator['shortname'] . '.xml')));
        header('ETag: ' . md5_file('cache/' . $legislator['shortname'] . '.xml'));
        readfile('cache/' . $legislator['shortname'] . '.xml');
        exit();
    }

    # Open a database connection.
    $database = new Database;
    $database->connect_old();

    # Query the database for information about that patron.
    $sql = 'SELECT representatives.id, representatives.name, representatives.chamber,
			representatives.shortname, representatives.party, districts.number AS district
			FROM representatives
			LEFT JOIN districts
				ON representatives.district_id=districts.id
			WHERE representatives.shortname = "' . mysqli_real_escape_string($legislator['shortname']) . '"';
    $result = mysqli_query($db, $sql);
    if (mysqli_num_rows($result) == 0)
    {
        die();
    }
    $legislator = mysqli_fetch_array($result);
    # Clean up some data.
    $legislator = array_map('stripslashes', $legislator);
    $legislator['suffix'] = '(' . $legislator['party'] . '-' . $legislator['district'] . ')';
    if ($legislator['chamber'] == 'house')
    {
        $legislator['prefix'] = 'Del.';
    }
    elseif ($legislator['chamber'] == 'senate')
    {
        $legislator['prefix'] = 'Sen.';
    }


    # Query the database for all bills.
    $sql = 'SELECT bills.number, bills.catch_line, bills.summary, sessions.year,
				(SELECT CONCAT_WS(", ", STATUS, DATE_FORMAT(DATE, "%m/%d/%Y"))
				FROM bills_status
				WHERE bills.id=bills_status.bill_id
				ORDER BY bills_status.date DESC, bills_status.id DESC
				LIMIT 1) AS status
			FROM bills
			LEFT JOIN representatives
				ON representatives.id=bills.chief_patron_id
			LEFT JOIN sessions
				ON bills.session_id = sessions.id
			WHERE bills.session_id = ' . SESSION_ID . '
			AND representatives.shortname="' . $legislator['shortname'] . '"
			ORDER BY bills.date_modified DESC';
    $result = mysqli_query($db, $sql);

    // Don't check to make sure the query was successful -- we want to make sure that people can
    // even subscribe to feeds for legislators that have introduced nothing yet.

    $rss_content = '';

    # Generate the RSS.
    while ($bill = mysqli_fetch_array($result))
    {

        # Aggregate the variables into their RSS components.
        $title = '<![CDATA[' . $bill['catch_line'] . '(' . mb_strtoupper($bill['number']) . ')]]>';
        $link = 'http://www.richmondsunlight.com/bill/' . $bill['year'] . '/' . $bill['number'] . '/';
        $description = '<![CDATA[<p>' . $bill['summary'] . '</p><p><strong>Status: ' . $bill['status'] . '</strong></p>]]>';

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
		<title>' . $legislator['prefix'] . ' ' . pivot($legislator['name']) . ' ' . $legislator['suffix'] . '</title>
		<link>http://www.richmondsunlight.com/bills/' . SESSION_YEAR . '/</link>
		<description>The bills filed by ' . pivot($legislator['name']) . ' in the ' . SESSION_YEAR . ' Virginia General Assembly session.</description>
		<language>en-us</language>
		' . $rss_content . '
	</channel>
</rss>';


    # Cache the RSS file.
    $fp = @file_put_contents('cache/' . $legislator['shortname'] . '.xml', $rss);

    header('Content-Type: application/xml');
    header('Last-Modified: ' . date('r', filemtime('cache/' . $legislator['shortname'] . '.xml')));
    header('ETag: ' . md5_file('cache/' . $legislator['shortname'] . '.xml'));
    echo $rss;
