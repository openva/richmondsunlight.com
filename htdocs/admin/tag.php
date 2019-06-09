<?php

###
# Tag Bills
#
# PURPOSE
# Provides administrative batch bill-tagging functionality.
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# PAGE METADATA
$page_title = 'Tag Bills';
$site_section = 'admin';

# PAGE CONTENT
if (!empty($_POST))
{

    $bills = $_POST['bill'];

    # Connect to Memcached.
    if (MEMCACHED_SERVER != '')
    {
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
    }

    # Iterate through every bill's tags.
    foreach ($bills as $bill_id => $tags)
    {

        # If we don't have any tags, just skip to the next bill.
        if (count($tags) == 0)
        {
            next;
        }

        # Explode the tags into an array to be inserted individually.
        $tag = explode(' ', $tags);

        for ($i=0; $i<count($tag); $i++)
        {
            # Trim it down.
            $tag[$i] = trim($tag[$i]);
            $tag[$i] = strtolower($tag[$i]);

            # If the string contains a quotation mark, build up a multiple-word
            # tag using everything up until the terminating quotation mark.
            if (stristr($tag[$i], '"'))
            {
                if (!isset($assembled_tag)) $assembled_tag = $tag[$i];
                else {
                    $tag[$i] = $assembled_tag.' '.$tag[$i];
                    $tag[$i] = str_replace('"', '', $tag[$i]);
                    unset($assembled_tag);
                }
            }

            elseif (isset($assembled_tag))
            {
                $assembled_tag .= ' '.$tag[$i];
            }

            # Don't proceed if it's blank.
            if ((!empty($tag[$i])) && (!isset($assembled_tag)))
            {

                # Make sure it's safe.
                $tag[$i] = preg_replace("/[[:punct:]]/D", '', $tag[$i]);
                $tag[$i] = trim(mysqli_real_escape_string($tag[$i]));

                # Check one more time to make sure it's not empty.
                if (!empty($tag[$i]))
                {

                    # Assemble the insertion SQL
                    $sql = 'INSERT INTO tags
							SET bill_id=' . $bill_id . ', tag="' . $tag[$i] . '",
							ip="' . $_SERVER['REMOTE_ADDR'] . '", user_id=
								(SELECT id
								FROM users
								WHERE cookie_hash = "' . $_SESSION['id'] . '"),
							date_created=now()';
                    $page_body .= '.';
                    mysqli_query($GLOBALS['db'], $sql);

                    # Delete this from the cache.
                    if (MEMCACHED_SERVER != '')
                    {
                        $mc->delete('bill-' . $bill_id);
                    }

                }
            }
        }
    }
}

else
{

    if (empty($_SESSION['id']))
    {
        die('Please log in before using this.');
    }

    /*
     * Count how many bills still aren't tagged.
     */
    $sql = 'SELECT COUNT(*) AS number
			FROM bills
			LEFT JOIN tags ON bills.id = tags.bill_id
			WHERE bills.session_id = ' . SESSION_ID . '
			AND tags.bill_id IS NULL';
    $result = mysqli_query($GLOBALS['db'], $sql);
    $remaining = mysqli_fetch_array($result);
    $page_body .= '<p>There are ' . number_format($remaining['number']) . ' bills that don’t have
		any tags.</p>';

    # Select twenty random bills that do not have tags. Order by session year (so that we begin
    # with the current/most recent session's bills), and then just by ID for lack of any better
    # idea.
    $sql = 'SELECT bills.id, bills.number, bills.catch_line, bills.summary, sessions.year
			FROM bills
			LEFT JOIN sessions
				ON bills.session_id=sessions.id
			WHERE
				(SELECT COUNT(*)
				FROM tags
				WHERE bill_id = bills.id) = 0
			ORDER BY sessions.year DESC, RAND()
			LIMIT 20';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) == 0)
    {
        die('Huzzah! There are no untagged bills!');
    }

    $page_body .= '
	<div id="bills">
		<form method="post" action="/admin/tag.php">';

    while ($bill = mysqli_fetch_array($result))
    {

        # If this bill doesn't have any tags (as, indeed, it should not), then generate some
        # candidate tags, using other bills that amend the same law(s).
        if (empty($bill['tags']))
        {
            $sql = 'SELECT COUNT( * ) AS number, tags.tag
					FROM tags
					LEFT JOIN bills AS b2
						ON tags.bill_id = b2.id
					LEFT JOIN bills_section_numbers AS bs2
						ON b2.id = bs2.bill_id
					LEFT JOIN bills_section_numbers
						ON bs2.section_number = bills_section_numbers.section_number
					LEFT JOIN bills
						ON bills_section_numbers.bill_id = bills.id
					WHERE bills.id =' . $bill['id'] . '
					GROUP BY tag
					HAVING number > 2
					ORDER BY number DESC';
            $tag_result = mysqli_query($GLOBALS['db'], $sql);
            if (mysqli_num_rows($result) > 0)
            {
                $tags = array();
                while ($tag = mysqli_fetch_array($tag_result))
                {

                    if (!isset($first_score))
                    {
                        $first_score = $tag['number'];
                    }

                    if (($tag['number'] / $first_score) > .5)
                    {

                        if (stristr($tag['tag'], ' ') !== FALSE)
                        {
                            $tag['tag'] = '"' . $tag['tag'] . '"';
                        }
                        $tags[] = $tag['tag'];

                    }

                }
                $bill['tags'] = implode(', ', $tags);
                unset($first_score);
            }
        }

        $page_body .= '
			<div class="bill">
				<div class="summary">
					<h2><a href="/bill/' . $bill['year'] . '/' . $bill['number'] . '/">'
                        . strtoupper($bill['number']) . '</a>: ' . $bill['catch_line'] . '</h2>
					'.nl2p($bill['summary']).'
				</div>
				<div class="tags">
					<textarea name="bill['.$bill['id'].']" style="width: 20em;">' . $bill['tags']
                        . '</textarea>
				</div>
			</div>';

    }

    $page_body .= '
			<input type="submit" value="Submit" />
		</form>
	</div>';
}

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
