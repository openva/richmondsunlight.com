<?php

    ###
    # Recommended Bills
    #
    # PURPOSE
    # Display a list of bills that an individual is likely to find interesting.
    #
    # NOTES
    # None.
    #
    # TODO
    # None.
    #
    ###

    # INCLUDES
    # Include any files or libraries that are necessary for this specific
    # page to function.
    include_once '../includes/settings.inc.php';
    include_once '../includes/functions.inc.php';

    # PAGE METADATA
    $page_title = 'Recommended Bills';
    $site_section = 'labs';

    # DECLARATIVE FUNCTIONS
    # Run those functions that are necessary prior to loading this specific
    # page.
    $database = new Database;
    $database->connect_old();

    # INITIALIZE SESSION
    session_start();

    # PAGE CONTENT
    $page_sidebar = <<<EOD

		<h3>Richmond Sunlight Labs</h3>
		<div class="box">

			<p><strong>This feature is experimental</strong>, a part of <a href="/labs/">Richmond
			Sunlight Labs</a>.</p>

			<p>We’re always trying something new here at Richmond Sunlight. Before it gets rolled
			out to the public (if it makes it to the public at all), you’ll see it here. Everything
			in here is in <em>beta,</em> meaning that any feature here may change drastically or
			simply disappear without notice, so consider yourself warned.</p>
		</div>
EOD;

    # See if the user is logged in.
    if (logged_in() === FALSE)
    {
        $page_body = '
		<p>This feature requires that you have first visited about a dozen bills of interest to
		you. Go spend some quality time reading legislation and return for your
		recommendations.</p>';
    }
    else
    {

        # If the user is logged in, get the user data.
        $user = get_user();

        # Make sure that this user has checked out at least ten tags' worth of bills.
        $sql = 'SELECT COUNT(*) AS count
				FROM tags
				LEFT JOIN bills_views
					ON tags.bill_id=bills_views.bill_id
				WHERE bills_views.user_id='.$user['id'];
        $result = mysqli_query($db, $sql);
        $tags = mysqli_fetch_array($result);
        if ($tags['count'] <= 10)
        {
            $page_body = '
			<p>This feature requires that you have first visited about a dozen bills of interest to
			you. Go spend some quality time reading legislation and return for your
			recommendations.</p>';
        }

        else
        {

            # We need to unset that $tags array from above.
            unset($tags);

            # Select the user's personal tag cloud.
            $sql = 'SELECT COUNT(*) AS count, tags.tag
					FROM bills_views
					LEFT JOIN tags
						ON bills_views.bill_id = tags.bill_id
					WHERE bills_views.user_id = '.$user['id'].' AND tag IS NOT NULL
					GROUP BY tags.tag
					ORDER BY count DESC';
            $result = mysqli_query($db, $sql);
            $page_sidebar .= '
				<h3>Your Tag Cloud</h3>
				<div class="box">
					<p>These are the topics in which you&rsquo;re most interested, as measured by the topics
					of the bills that you&rsquo;ve looked at.</p>

					<div class="tags">';

            # Build up an array of tags, with the key being the tag and the value being the count.
            while ($tag = mysqli_fetch_array($result))
            {
                $tag = array_map('stripslashes', $tag);
                $tags[$tag{'tag'}] = $tag['count'];
            }

            # Sort the tags in reverse order by key (their count), shave off the top 30, and then
            # resort alphabetically.
            arsort($tags);
            $tags = array_slice($tags, 0, 30, true);
            $tag_data['biggest'] = max(array_values($tags));
            $tag_data['smallest'] = min(array_values($tags));
            ksort($tags);

            # Set the smallest and largest font sizes that we'll accept here, in %.
            $font['max'] = 250;
            $font['min'] = 100;

            # Determine the distance between the smallest and the largest tags.
            $tag_data['spread'] = $tag_data['biggest'] - $tag_data['smallest'];

            # If the smallest and largest tags are the same size, let's avoid dividing by zero, and
            # declare a spread of one.
            if ($tag_data['spread'] == 0)
            {
                $tag_data['spread'] = 1;
            }

            $step = ($font['max'] - $font['min']) / $tag_data['spread'];
            foreach ($tags as $tag => $count)
            {
                # Establish the font size of this tag in a round percentage (no decimals).
                $font_size = round($font['min'] + (($count - $tag_data['smallest']) * $step));
                $page_sidebar .= '
						<span style="font-size: '.$font_size.'%;">
							<a href="/bills/tags/'.urlencode($tag).'/">'.$tag.'</a>
						</span>';
            }
            $page_sidebar .= '
					</div>
				</div>';

            $page_body .= '
				<p>The following bills from the '.SESSION_YEAR.' session, which you have not yet
				seen on Richmond Sunlight, are likely to be of interest to you, given the bills that
				you tend to be interested in.</p>';

            // The use of a subselect to make sure that this person has never before seen this bill
            // slows down this query a lot, making it take 2.5x longer than it would otherwise
            // (.08 seconds vs .2 seconds). It would be good to rewrite that bit as a join.
            $sql = 'SELECT DISTINCT bills.id, bills.number, bills.catch_line,
					DATE_FORMAT(bills.date_introduced, "%M %d, %Y") AS date_introduced,
					committees.name, sessions.year,

					(
						SELECT translation
						FROM bills_status
						WHERE bill_id=bills.id AND translation IS NOT NULL
						ORDER BY date DESC, id DESC
						LIMIT 1
					) AS status,

						(SELECT COUNT(*)
						FROM bills AS bills2 LEFT JOIN tags AS tags2 ON bills2.id=tags2.bill_id
						WHERE (';
            # Using an array of tags established above, when listing the bill's tags, iterate
            # through them to create the SQL. The actual tag SQL is built up and then reused,
            # though slightly differently, later on in the SQL query, hence the str_replace.
            $tags_sql = '';
            foreach ($tags as $tag=>$tmp)
            {
                $tags_sql .= 'tags2.tag = "'.$tag.'" OR ';
            }
            # Hack off the final " OR "
            $tags_sql = substr($tags_sql, 0, -4);
            $sql .= $tags_sql;
            $tags_sql = str_replace('tags2', 'tags', $tags_sql);
            $sql .= ')
						AND bills2.id = bills.id
						) AS count,

						(SELECT COUNT(*)
						FROM bills_views
						WHERE bill_id=bills.id AND user_id='.$user['id'].'
						) = 0
					FROM bills
					LEFT JOIN tags ON bills.id=tags.bill_id
					LEFT JOIN sessions ON bills.session_id=sessions.id
					LEFT JOIN committees ON bills.last_committee_id = committees.id
					WHERE ('.$tags_sql.') AND bills.session_id = '.SESSION_ID.'
					HAVING count > 0
					ORDER BY count DESC
					LIMIT 10';
            $result = mysqli_query($db, $sql);
            if (mysqli_num_rows($result) == 0)
            {
                $page_body .= '<p>Sorry, no bills could be found that appear to match your tastes
				that you haven’t already seen. Most likely, you just need to spend some more time
				reading bills that you find interesting, so that Richmond Sunlight can learn more
				about your tastes. Alternately, you have such a voracious appetite for bills that
				there simply aren’t any bills that you haven’t read. The former is probably more
				likely.</p>';
            }
            else
            {
                $page_body .= '<ul>';
                while ($bill = mysqli_fetch_array($result))
                {
                    $bill = array_map('stripslashes', $bill);
                    $page_body .= '<li><a href="/bill/'.$bill['year'].'/'.$bill['number'].'/">'
                        .strtoupper($bill['number']).'</a>: '.$bill['catch_line'].'</li>';
                }
                $page_body .= '</ul>';
            }
        }
    }

    # OUTPUT THE PAGE
    display_page('page_title='.$page_title.'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
        '&site_section='.urlencode($site_section));
