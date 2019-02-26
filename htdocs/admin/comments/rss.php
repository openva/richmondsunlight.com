<?php

    ###
    # Comment Activity RSS
    #
    # PURPOSE
    # Lists the last 40 comments posted.
    #
    # TODO
    # * Support If-Modified-Since and If-None-Match headers to reduce bandwidth.
    # * The session year in the RSS URL is hard-coded due to soem kind of a weird
    #   MySQL join error.  Fix that prior to 2008.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once $_SERVER['DOCUMENT_ROOT'].'/includes/settings.inc.php';
    include_once $_SERVER['DOCUMENT_ROOT'].'/includes/functions.inc.php';

    # PAGE CONTENT

    # Open a database connection.
    $database = new Database;
    $database->connect_old();

    # Query the database for the last 40 comments.
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
			ORDER BY comments.date_created DESC
			LIMIT 40';
    $result = mysql_query($sql);

    $rss_content = '';

    # Generate the RSS.
    while ($comment = mysql_fetch_array($result))
    {

        # Aggregate the variables into their RSS components.
        $title = '<![CDATA['.$comment['name'].' '.strtoupper($comment['bill_number']).']]>';
        $link = 'http://www.richmondsunlight.com/bill/'.$comment['year'].'/'.$comment['bill_number'].'/#comment-'.$comment['number'];
        $description = '<![CDATA[
			<p>'.nl2br($comment['comment']).'</p>
			<ul>
				<li><a href="http://www.richmondsunlight.com/admin/comments/?op=spam&amp;id='.$comment['id'].'">Mark as Spam</a></li>
				<li><a href="http://www.richmondsunlight.com/admin/comments/?op=delete&amp;id='.$comment['id'].'">Delete</a></li>
				<li><a href="http://www.richmondsunlight.com/admin/comments/?op=edit&amp;id='.$comment['id'].'">Edit</a></li>
			</ul>
			]]>';

        # Now assemble those RSS components into an XML fragment.
        $rss_content .= '
		<item>
			<title>'.$title.'</title>
			<link>'.$link.'</link>
			<description>'.$description.'</description>
		</item>';

        # Unset those variables for reuse.
        unset($item_completed, $title, $link, $description);




    }



    $rss = '<?xml version="1.0" encoding=\'utf-8\'?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
	<channel>
		<title>Richmond Sunlight Comments</title>
		<link>http://www.richmondsunlight.com/admin/comments/</link>
		<description>The admin comments-monitoring feed.</description>
		<language>en-us</language>
		'.$rss_content.'
	</channel>
</rss>';

    echo $rss;
