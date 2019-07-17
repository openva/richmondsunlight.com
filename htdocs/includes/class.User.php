<?php

class User
{

    /*
     * A wrapper around get_user(), in functions.inc.php.
     */
    public function get()
    {
        $this->data = get_user();

        if ($this->data == FALSE)
        {
            return FALSE;
        }

        return TRUE;
    }

    /*
     * A reimplementation logged_in() function, in functions.inc.php, but that returns not just
     * TRUE or FALSE, but also whether the user is registered.
     */
    public function logged_in($check_if_registered = '')
    {

        /*
         * If there's no session ID, they can't be registered.
         */
        if (empty($_SESSION['id']))
        {
            return FALSE;
        }

        /*
         * If this session ID is stored in Memcached, then we don't need to query the database.
         */
        if (MEMCACHED_SERVER != '')
        {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            $result = $mc->get('user-session-' . $_SESSION['id']);
            if ($mc->getResultCode() === 0)
            {

                /*
                * Indicate whether this is a registered user -- that is, somebody who has actually
                * created an account. (That's the value of "user-session-[id]" -- true or false.)
                */
                $this->registered = $result;

                /*
                * Report that this is a user.
                */
                return TRUE;

            }
        
        }

        $database = new Database;
        $database->connect_mysqli();

        $sql = 'SELECT id, password
				FROM users
				WHERE cookie_hash="' . mysqli_real_escape_string($GLOBALS['db'], $_SESSION['id']) . '"';
        $result = mysqli_query($GLOBALS['db'], $sql);

        if (mysqli_num_rows($result) == 1)
        {
            $registered = mysqli_fetch_assoc($result);

            /*
             * The presence of a password indicates that it's a user who has created an account.
             */
            if (!empty($registered['password']))
            {
                $this->registered = TRUE;
            }
            else
            {
                $this->registered = FALSE;
            }

            /*
             * Store this session in Memcached for the next 30 minutes.
             */
            if (MEMCACHED_SERVER != '')
            {
                $mc->set('user-session-' . $_SESSION['id'], $this->registered, (60 * 30));
            }

            return TRUE;

        }

        return FALSE;
    }

    public function views_cloud()
    {

        # The user must be logged in.
        if (logged_in() !== TRUE)
        {
            return FALSE;
        }

        $database = new Database;
        $database->connect_mysqli();

        # Get the user's account data.
        $user = get_user();

        # Select the user's personal tag cloud. We don't want a crazy number of tags, so cap it
        # at 100.
        $sql = 'SELECT COUNT(*) AS count, tags.tag
				FROM bills_views
				LEFT JOIN tags
					ON bills_views.bill_id = tags.bill_id
				WHERE bills_views.user_id = ' . $user['id'] . ' AND tag IS NOT NULL
				GROUP BY tags.tag
				ORDER BY count DESC
				LIMIT 100';
        $result = mysqli_query($GLOBALS['db'], $sql);

        # Unless we have ten tags, we just don't have enough data to continue.
        if (mysqli_num_rows($result) < 10)
        {
            return FALSE;
        }

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

        return $tags;
    }

    # Provide a listing of bills that this bill has not seen, but would probably be interested
    # in. This works by getting tag cloud data for this user's bill views, using that raw data
    # to query a list of bills that he's liable to be interestd in, and then substracting out a
    # list of every bill that he's already seen.
    public function recommended_bills()
    {

        # Get the user's account data.
        $user = get_user();

        /*
         * Connect to Memcached.
         */
        if (MEMCACHED_SERVER != '')
        {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);

            # Get a list of recommended bills for this user.
            $result = $mc->get('recommendations-' . $user['id']);
            if ($mc->getResultCode() === 0)
            {
                $bills = unserialize($result);
                if ($bills !== FALSE)
                {
                    return $bills;
                }
            }
        }

        $tags = User::views_cloud();

        if ($tags === FALSE)
        {
            return FALSE;
        }

        $database = new Database;
        $database->connect_mysqli();

        # Get a list of every bill that this user has looked at.
        $sql = 'SELECT DISTINCT bills_views.bill_id AS id
				FROM bills_views
				LEFT JOIN bills
					ON bills_views.bill_id = bills.id
				WHERE bills.session_id = ' . SESSION_ID . ' AND user_id = ' . $user['id'];
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0)
        {
            $bills_seen = array();
            while ($bill = mysqli_fetch_assoc($result))
            {
                $bills_seen[$bill{'id'}] = true;
            }
        }

        # Now get a list of every bill that this user is liable to be interested in, including ones
        # that he's seen before.
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
				(
					SELECT COUNT(*)
					FROM bills AS bills2
					LEFT JOIN tags AS tags2
						ON bills2.id=tags2.bill_id
					WHERE (';
        # Using an array of tags established above, when listing the bill's tags, iterate
        # through them to create the SQL. The actual tag SQL is built up and then reused,
        # though slightly differently, later on in the SQL query, hence the str_replace.
        $tags_sql = '';
        foreach ($tags as $tag=>$tmp)
        {
            $tags_sql .= 'tags2.tag = "' . $tag . '" OR ';
        }
        # Hack off the final " OR "
        $tags_sql = mb_substr($tags_sql, 0, -4);
        $sql .= $tags_sql;
        $tags_sql = str_replace('tags2', 'tags', $tags_sql);
        $sql .= ')
						AND bills2.id = bills.id
					) AS count

				FROM bills
				LEFT JOIN tags
					ON bills.id=tags.bill_id
				LEFT JOIN sessions
					ON bills.session_id=sessions.id
				LEFT JOIN committees
					ON bills.last_committee_id = committees.id
				WHERE (' . $tags_sql . ')
				AND bills.session_id = ' . SESSION_ID . '
				HAVING count > 2
				ORDER BY count DESC
				LIMIT 100';

        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0)
        {
            return FALSE;
        }
        else
        {
            while ($bill = mysqli_fetch_assoc($result))
            {
                $bill = array_map('stripslashes', $bill);
                if (!isset($bills_seen[$bill{'id'}]))
                {
                    $bills[] = $bill;
                }
            }
        }

        # Now hack off the top 10 bills.
        $bills = array_slice($bills, 0, 10);

        # Store this user's recommendations in Memcached for the next half-hour. We keep it brief
        # because user sessions are unlikely to last longer than this and to allow their
        # recommendations to be updated as new bills are filed, as they view their recommended
        # bills, and as they view bills that cause their recommendations to change.
        if (MEMCACHED_SERVER != '')
        {
            $mc->set('recommendations-' . $user['id'], serialize($bills), (60 * 30));
        }

        return $bills;
    }

    # List legislation in the current session that cite places physically near to the user. This is
    # based on the most ghetto possible geolocation comparison -- choosing bills with a latitude
    # and longitude that are within a fraction of a degree of the user's location and ordering the
    # resulting list by the size of the difference between the two. I'm not proud, but it's a great
    # deal easier than installing spatial extensions to MySQL.
    public function nearby_bills()
    {

        # Get the user's account data.
        $user = get_user();

        if (!isset($user['latitude']) || !isset($user['longitude']))
        {
            return FALSE;
        }

        $database = new Database;
        $database->connect_mysqli();

        $sql = 'SELECT bills.id, bills.number, bills.catch_line, sessions.year,
				bills_places.placename, bills_places.latitude, bills_places.longitude,
				(' . $user['latitude'] . ' - bills_places.latitude) AS lat_diff,
				(' . $user['longitude'] . ' - bills_places.longitude) AS lon_diff
				FROM bills_places
				LEFT JOIN bills
					ON bills_places.bill_id = bills.id
				LEFT JOIN sessions
					ON bills.session_id=sessions.id
				WHERE (latitude >= ' . (round($user['latitude'], 1)-.25) . '
				AND latitude <=' . (round($user['latitude'], 1)+.25) . ')
				AND (longitude <= ' . (round($user['longitude'], 1)+.25) . '
				AND longitude >= ' . (round($user['longitude'], 1)-.25) . ')
				AND bills.session_id = ' . SESSION_ID . '
				ORDER BY ( lat_diff + lon_diff ) DESC';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0)
        {
            return FALSE;
        }

        $bills = array();

        while ($bill = mysqli_fetch_array($result))
        {
            $bills[] = array_map('stripslashes', $bill);
        }

        return $bills;
    }

    # Provide some statistics about this user's tagging habits.
    public function tagging_stats()
    {

        # The user must be logged in.
        if (logged_in() !== TRUE)
        {
            return FALSE;
        }

        # Get the user's account data.
        $user = get_user();

        $database = new Database;
        $database->connect_mysqli();

        $sql = 'SELECT
					(SELECT COUNT(*)
					FROM tags
					WHERE user_id=' . $user['id'] . ') AS tags,

					(SELECT COUNT(DISTINCT(bill_id))
					FROM tags
					WHERE user_id=' . $user['id'] . ') AS bills';

        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0)
        {
            return FALSE;
        }
        $stats = mysqli_fetch_object($result);
        return $stats;
    }

    # Get a listing of the comments by this user.
    public function list_comments()
    {

        # The user must be logged in.
        if (logged_in() !== TRUE)
        {
            return FALSE;
        }

        # Get the user's account data.
        $user = get_user();

        $database = new Database;
        $database->connect_mysqli();

        $sql = 'SELECT sessions.year AS bill_year, bills.number AS bill_number,
				bills.catch_line,
				DATE_FORMAT(comments.date_created, "%m/%d/%Y") AS date, comments.comment
				FROM comments
				LEFT JOIN bills
					ON comments.bill_id = bills.id
				LEFT JOIN sessions
					ON bills.session_id = sessions.id
				WHERE comments.user_id =' . $user['id'] . '
				AND comments.status = "published"
				ORDER BY comments.date_created DESC
				LIMIT 10';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) == 0)
        {
            return FALSE;
        }
        while ($comment = mysqli_fetch_assoc($result))
        {
            $comments[] = $comment;
        }
        return $comments;
    }

    /**
     * Delete a user.
     **/
    public function delete()
    {

        if (!isset($this->id))
        {
            return FALSE;
        }

        $sql = 'DELETE FROM users
                WHERE id=' . $this->id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        if ($result != TRUE)
        {
            return FALSE;
        }

        return TRUE;

    }

}
