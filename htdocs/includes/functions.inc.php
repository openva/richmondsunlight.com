<?php

###
# Functions Library
#
# PURPOSE
# All of the non-class-based functions that may be used on any page on the site. Class-based
# functions are autoloaded.
#
###

# Any function that isn't included here will be included if it is requested.
function __autoload_libraries($name)
{
    if (php_sapi_name() == 'cli')
    {
        $includes_dir = __DIR__ . '/';
    }
    else
    {
        $includes_dir = realpath($_SERVER['DOCUMENT_ROOT']) . '/includes/';
    }

    if (file_exists($includes_dir . 'class.' . $name . '.php') === TRUE)
    {
        include 'class.' . $name . '.php';
        return TRUE;
    }
}

spl_autoload_register('__autoload_libraries');

# Connect to the database
function connect_to_db($type = 'old')
{

    if ($type == 'old')
    {
        $database = new Database;
        $database->connect_old();
    }
    elseif ($type == 'pdo')
    {
        $database = new Database;
        $database->connect();
    }

    return false;

}



# Makes sure that an e-mail address has a valid format.
function validate_email($email)
{
    if (empty($email))
    {
        return FALSE;
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        return true;
    }
    return FALSE;
}



# Pivots text around a comma.  God knows what it would do if there was more than one comma. Good
# for lastname, firstname type things.  If there is no comma, it just spits the text back out, happy
# as can be.  If there's no text, then it returns false.
function pivot($text)
{
    if (empty($text))
    {
        return FALSE;
    }
    if (mb_strpos($text, ', ') !== false)
    {
        $str = explode(",", $text);
        $text = $str[1] . ' ' . $str[0];
        return trim($text);
    }
    return $text;
}

function array_map_multi($func, $arr)
{
    $newArr = array();
    foreach ($arr as $key => $value)
    {
        $newArr[$key] = (is_array($value) ? array_map_multi($func, $value) : $func($value));
    }
    return $newArr;
}

# Displays the search form. This is really only meant for use on the search page.
function search_form($q)
{
    $q = trim($q);
    $returned_data = '
		<form method="get" action="/search/">
		<input type="text" name="q" class="search" size="50" ';
    if (!empty($q))
    {
        $q = htmlspecialchars($q);
        $returned_data .= 'value="' . $q . '" ';
    }
    $returned_data .= '/> <input type="submit" value="Search" class="submit" />';
    return $returned_data;
}

# Spam-proof an e-mail address.
function spam_proof($email)
{
    if (empty($email))
    {
        return FALSE;
    }
    $email = str_replace('@', '&#064;', $email);
    return $email;
}

# Convert a number of seconds to a more reasonable unit.  This is for use as the datestamp on
# comments, which uses a Flickr-style time-elapsed-since-posting datestamp.
function seconds_to_units($seconds)
{
    if ($seconds == 0)
    {
        $returned_data = 'just now';
    }
    else
    {
        if ($seconds < 60)
        {
            $returned_data = $seconds . ' second';
        }
        elseif ($seconds < 3600)
        {
            $returned_data = round($seconds / 60) . ' minute';
        }
        elseif ($seconds < 86400)
        {
            $returned_data = round($seconds / 60 / 60) . ' hour';
        }
        elseif ($seconds < 2592000)
        {
            $returned_data = round($seconds / 60 / 60 / 24) . ' day';
        }
        elseif ($seconds >= 2592000)
        {
            $returned_data = round($seconds / 60 / 60 / 24 / 30) . ' month';
        }

        if (mb_substr($returned_data, 0, 2) != '1 ')
        {
            $returned_data .= 's';
        }
        $returned_data .= ' ago';
    }

    return $returned_data;
}

# Convert a HH:MM:SS timestamp to the total number of seconds represented by that elapsed time.
function time_to_seconds($time = '00:00:00')
{
    list($h, $m, $s) = explode(':', $time);
    return ($h * 3600) + ($m * 60) + $s;
}

# Convert a number of seconds to a HH:MM:SS timestamp.
function seconds_to_time($seconds, $lpad = false)
{
    return gmdate('H:i:s', $seconds);
}


# Create a new user. We wrap the function in function_exists() because WordPress 2.6 turns out
# to use exactly the same function name, thus preventing the Richmond Sunlight blog from
# working. So here's the solution. We don't need create_user() on the blog, anyway.
if (!function_exists('create_user'))
{
    function create_user($options)
    {
        # Turn the URL-style options into an array.
        parse_str($options, $options);

        $options = array_map('urldecode', $options);

        # If this isn't a Dashboard user, parse the variables in the standard way.
        if ($options['dashboard'] != 'y')
        {
            if (count($options) > 0)
            {
                $sql_inserts = '';
                foreach ($options as $key => $value)
                {
                    $value = mysqli_real_escape_string($GLOBALS['db'], $value);
                    if (empty($value))
                    {
                        $sql_inserts .= ', ' . $key . ' = NULL';
                    }
                    else
                    {
                        $sql_inserts .= ', ' . $key . ' = "' . $value . '"';
                    }
                }
            }
        }

        # But if this is a Dashboard user, parse the variables out into two separate SQL inserts.
        elseif ($options['dashboard'] == 'y')
        {
            if (count($options) > 0)
            {
                $users_inserts = '';
                $dashboard_inserts = '';
                foreach ($options as $key => $value)
                {
                    # Make the data safe for the database.
                    $value = mysqli_real_escape_string($GLOBALS['db'], $value);

                    # Determine which SQL string this data should be appended to.
                    if (($key == 'organization') || ($key == 'type') || ($key == 'expires'))
                    {
                        $dashboard_inserts .= ', ' . $key . ' = "' . $value . '"';
                    }
                    elseif ($key == 'dashboard')
                    {
                        while (false);
                    }
                    else
                    {

                        /*
                         * Handle passwords differently, since they need to be hashed.
                         */
                        if ($key == 'password')
                        {
                            /*
                             * Sometimes we're getting blank passwords. I'm not sure what that's
                             * about. So make sure they're not empty.
                             */
                            if (trim($value) != '')
                            {
                                $users_inserts .= ', ' . $key . ' = MD5("' . $value . '")';
                            }
                        }
                        else
                        {
                            $users_inserts .= ', ' . $key . ' = "' . $value . '"';
                        }
                    }
                }
            }
        }

        # Generate a session ID -- the cookie hash.
        $_SESSION['id'] = create_session_id();

        # Insert the user data.
        $sql = 'INSERT INTO users
				SET cookie_hash="' . $_SESSION['id'] . '",
				ip="' . $_SERVER['REMOTE_ADDR'] . '", date_created=now()';
        if (!empty($users_inserts))
        {
            $sql .= $users_inserts;
        }
        if (!empty($sql_inserts))
        {
            $sql .= $sql_inserts;
        }
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (!$result)
        {
            return FALSE;
        }
        if ($options['dashboard'] == 'y')
        {

            # Get the user's ID.
            $user_id = mysqli_insert_id($GLOBALS['db']);

            # Generate a random eight-digit hash to send out in e-mails for unsubscribing
            # instantly.
            $chars = 'bcdfghjklmnpqrstvxyz0123456789';
            $hash = mb_substr(str_shuffle($chars), 0, 8);

            # Insert the Dashboard user data.
            $sql = 'INSERT INTO dashboard_user_data
					SET user_id = ' . $user_id . ', email_active="y", last_access=now(),
					date_created=now(), unsub_hash="' . $hash . '"';
            if (!empty($dashboard_inserts))
            {
                $sql .= $dashboard_inserts;
            }
            mysqli_query($GLOBALS['db'], $sql);
        }
    }
}

# Retrieve a user's data, returning an array.
function get_user()
{
    if (!isset($_SESSION['id']))
    {
        return FALSE;
    }

    
    # If we have a record of this user cached in Memcached, use that instead.
    if (MEMCACHED_SERVER != '')
    {

        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $result = $mc->get('user-' . $_SESSION['id']);
        if ($mc->getResultCode() === 0)
        {
            if ($result != FALSE)
            {
                return $result;
            }
        }

    }

    $sql = 'SELECT users.id, users.name, users.password, users.email, users.url,
			users.zip, users.city, users.state, users.house_district_id, users.senate_district_id,
			users.latitude, users.longitude, users.trusted, users.ip, users.date_modified,
			users.date_created, users.private_hash, dashboard_user_data.organization,
			dashboard_user_data.type, dashboard_user_data.last_access
			FROM users
			LEFT JOIN dashboard_user_data
				ON users.id=dashboard_user_data.user_id
			WHERE users.cookie_hash="' . mysqli_real_escape_string($GLOBALS['db'], $_SESSION['id']) . '"';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) == 0)
    {
        return FALSE;
    }
    $user = mysqli_fetch_array($result, MYSQL_ASSOC);
    $user = array_map('stripslashes', $user);

    # Cache this user's data, and save it for one hour. (User sessions are unlikely to last longer.)
    if (MEMCACHED_SERVER != '')
    {
        $mc->set('user-' . $_SESSION['id'], $user, (60 * 60));
    }

    return $user;
}

# Update a user's data.
function update_user($options)
{
    parse_str($options, $options);
    if (count($options) < 1)
    {
        return FALSE;
    }
    if (empty($_SESSION['id']))
    {
        return FALSE;
    }

    # If this user's data is cached in APC, delete it, since it's now out of date.
    if (apc_exists('user-' . $_SESSION['id']) !== false)
    {
        apc_delete('user-' . $_SESSION['id']);
    }

    # Assemble the SQL string.
    $sql = 'UPDATE users SET ';
    $first = 'yes';
    foreach ($options as $key => $value)
    {
        $value = mysqli_real_escape_string($GLOBALS['db'], $value);
        if (!isset($first))
        {
            $sql .= ', ';
        }
        else
        {
            unset($first);
        }
        $sql .= $key . ' = "' . $value . '"';
    }
    $sql .= ' WHERE cookie_hash="' . mysqli_real_escape_string($GLOBALS['db'], $_SESSION['id']) . '"';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (!$result)
    {
        return FALSE;
    }
    return true;
}

# Generate a random session ID.
function create_session_id()
{
    return md5(uniqid(rand(1000000, 9999999), true));
}


# Determine whether the current user has an account or, more accurately, whether he's logged into an
# account. If we pass the word "registered" to this function, then it goes a step further and checks
# to see whether this user hasn't just interacted in a way that has made a faux account, but has
# actually signed up.
#
# NOTE that this functionality is duplicated -- and modernized -- in the User class.
function logged_in($registered = '')
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
            return TRUE;
        }
    
    }

    /*
     * If this is a registered visitor (as opposed to somebody who has posted comments,
     * voted, etc., but hasn't created an account).
     */
    if ($registered == 'registered')
    {
        $sql = 'SELECT id
				FROM users
				WHERE cookie_hash="' . mysqli_real_escape_string($GLOBALS['db'], $_SESSION['id']) . '"
				AND password IS NOT NULL';
    }

    /*
     * If this is not a registered user.
     */
    else
    {
        $sql = 'SELECT id
				FROM users
				WHERE cookie_hash="' . mysqli_real_escape_string($GLOBALS['db'], $_SESSION['id']) . '"';
    }
    $result = mysqli_query($GLOBALS['db'], $sql);

    /*
     * If one result is returned, then this user does have an account.
     */
    if (mysqli_num_rows($result) == 1)
    {
        if ($registered == 'registered')
        {
            $is_registered = TRUE;
        }
        else
        {
            $is_registered = FALSE;
        }
        /*
         * Store this session in Memcached for the next 30 minutes.
         */
        if (MEMCACHED_SERVER != '')
        {
            $mc->set('user-session-' . $_SESSION['id'], $is_registered, (60 * 30));
        }
        
        return true;

    }

    return FALSE;
}

# Determine whether the current user is blacklisted from participating, by looking at his cumulative
# score and, if it's 20 or greater, prevent him from participating.
function blacklisted()
{
    $sql = 'SELECT SUM(score) AS score
			FROM blacklist
			WHERE ip="' . $_SERVER['REMOTE_ADDR'] . '" OR user_id =
				(SELECT id
				FROM users
				WHERE cookie_hash = "' . $_SESSION['id'] . '")';
    $result = mysqli_query($GLOBALS['db'], $sql);
    $data = mysqli_fetch_array($result);
    $score = $data['score'];
    if ($score >= 20)
    {
        return true;
    }
    else
    {
        return FALSE;
    }
}

# Add the current user to the blacklist.
function blacklist($word)
{
    $sql = 'INSERT INTO blacklist
			SET ip="' . $_SERVER['REMOTE_ADDR'] . '", user_id =
				(SELECT id
				FROM users
				WHERE cookie_hash = "' . $_SESSION['id'] . '"),
			date_created=now(),
			score=20';
    if (isset($word))
    {
        $sql .= ', reason="dirty word - ' . $word . '"';
    }

    mysqli_query($GLOBALS['db'], $sql);
}

# Display the CSS balloon providing a tooltip-type interface for bills and legislators.
function balloon($bill, $type)
{
    // We don't use this anymore, but there are still calls to it.
    // DON'T return true. Due to an evaluation problem, it will insert a "1"!
    return FALSE;
}


# Convert the abbreviated, database-stored description of a bill's
# status into meaningful description.
function explain_status($status)
{
    if (empty($status))
    {
        return FALSE;
    }
    if ($status == 'continued')
    {
        return 'Continued to Next Session';
    }
    if ($status == 'introduced')
    {
        return 'Introduced';
    }
    if ($status == 'committee')
    {
        return 'In Committee';
    }
    if ($status == 'in committee')
    {
        return 'In Committee';
    }
    if ($status == 'in subcommittee')
    {
        return 'In Subcommittee';
    }
    if ($status == 'failed subcommittee')
    {
        return 'Subcommittee Recommends Killing the Bill';
    }
    if ($status == 'passed subcommittee')
    {
        return 'Subcommittee Recommends Passing the Bill';
    }
    if ($status == 'failed committee')
    {
        return 'Failed to Pass in Committee';
    }
    if ($status == 'stricken')
    {
        return 'Bill Killed at Sponsor’s Request';
    }
    if ($status == 'passed house')
    {
        return 'Passed the House';
    }
    if ($status == 'passed senate')
    {
        return 'Passed the Senate';
    }
    if ($status == 'passed')
    {
        return 'Passed the General Assembly';
    }
    if ($status == 'failed')
    {
        return 'Failed to Advance';
    }
    if ($status == 'approved')
    {
        return 'Signed into Law';
    }
    if ($status == 'incorporated')
    {
        return 'Incorporated into Another Bill';
    }
    else
    {
        return $status;
    }
}

# A simple wrapper for CURL.
function get_content($url, $timeout=10)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $string = curl_exec($ch);
    curl_close($ch);

    if (empty($string))
    {
        return FALSE;
    }

    return $string;
}

# Given a district number and a chamber, returns its database ID.
function district_to_id($number, $chamber)
{
    if (!isset($number) || !isset($chamber))
    {
        return FALSE;
    }

    # Select the information from the database.
    $sql = 'SELECT id
			FROM districts
			WHERE number = ' . $number . ' AND chamber = "' . $chamber . '"
			AND date_ended IS NULL';
    $result = mysqli_query($GLOBALS['db'], $sql);

    # Continue if we've got a match.
    if (mysqli_num_rows($result) > 0)
    {
        # Return the database ID.
        $district = mysqli_fetch_array($result);
        return $district['id'];
    }

    return FALSE;
}

# nl2p is WordPress' wpautop(), renamed
function nl2p($pee, $br = true)
{
    
	$pre_tags = array();

	if ( trim( $pee ) === '' ) {
		return '';
	}

	// Just to make things a little easier, pad the end.
	$pee = $pee . "\n";

	/*
	 * Pre tags shouldn't be touched by autop.
	 * Replace pre tags with placeholders and bring them back after autop.
	 */
	if ( strpos( $pee, '<pre' ) !== false ) {
		$pee_parts = explode( '</pre>', $pee );
		$last_pee  = array_pop( $pee_parts );
		$pee       = '';
		$i         = 0;

		foreach ( $pee_parts as $pee_part ) {
			$start = strpos( $pee_part, '<pre' );

			// Malformed html?
			if ( $start === false ) {
				$pee .= $pee_part;
				continue;
			}

			$name              = "<pre wp-pre-tag-$i></pre>";
			$pre_tags[ $name ] = substr( $pee_part, $start ) . '</pre>';

			$pee .= substr( $pee_part, 0, $start ) . $name;
			$i++;
		}

		$pee .= $last_pee;
	}
	// Change multiple <br>s into two line breaks, which will turn into paragraphs.
	$pee = preg_replace( '|<br\s*/?>\s*<br\s*/?>|', "\n\n", $pee );

	$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

	// Add a double line break above block-level opening tags.
	$pee = preg_replace( '!(<' . $allblocks . '[\s/>])!', "\n\n$1", $pee );

	// Add a double line break below block-level closing tags.
	$pee = preg_replace( '!(</' . $allblocks . '>)!', "$1\n\n", $pee );

	// Standardize newline characters to "\n".
	$pee = str_replace( array( "\r\n", "\r" ), "\n", $pee );

	// Find newlines in all elements and add placeholders.
	$pee = wp_replace_in_html_tags( $pee, array( "\n" => ' <!-- wpnl --> ' ) );

	// Collapse line breaks before and after <option> elements so they don't get autop'd.
	if ( strpos( $pee, '<option' ) !== false ) {
		$pee = preg_replace( '|\s*<option|', '<option', $pee );
		$pee = preg_replace( '|</option>\s*|', '</option>', $pee );
	}

	/*
	 * Collapse line breaks inside <object> elements, before <param> and <embed> elements
	 * so they don't get autop'd.
	 */
	if ( strpos( $pee, '</object>' ) !== false ) {
		$pee = preg_replace( '|(<object[^>]*>)\s*|', '$1', $pee );
		$pee = preg_replace( '|\s*</object>|', '</object>', $pee );
		$pee = preg_replace( '%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $pee );
	}

	/*
	 * Collapse line breaks inside <audio> and <video> elements,
	 * before and after <source> and <track> elements.
	 */
	if ( strpos( $pee, '<source' ) !== false || strpos( $pee, '<track' ) !== false ) {
		$pee = preg_replace( '%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $pee );
		$pee = preg_replace( '%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $pee );
		$pee = preg_replace( '%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $pee );
	}

	// Collapse line breaks before and after <figcaption> elements.
	if ( strpos( $pee, '<figcaption' ) !== false ) {
		$pee = preg_replace( '|\s*(<figcaption[^>]*>)|', '$1', $pee );
		$pee = preg_replace( '|</figcaption>\s*|', '</figcaption>', $pee );
	}

	// Remove more than two contiguous line breaks.
	$pee = preg_replace( "/\n\n+/", "\n\n", $pee );

	// Split up the contents into an array of strings, separated by double line breaks.
	$pees = preg_split( '/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY );

	// Reset $pee prior to rebuilding.
	$pee = '';

	// Rebuild the content as a string, wrapping every bit with a <p>.
	foreach ( $pees as $tinkle ) {
		$pee .= '<p>' . trim( $tinkle, "\n" ) . "</p>\n";
	}

	// Under certain strange conditions it could create a P of entirely whitespace.
	$pee = preg_replace( '|<p>\s*</p>|', '', $pee );

	// Add a closing <p> inside <div>, <address>, or <form> tag if missing.
	$pee = preg_replace( '!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $pee );

	// If an opening or closing block element tag is wrapped in a <p>, unwrap it.
	$pee = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $pee );

	// In some cases <li> may get wrapped in <p>, fix them.
	$pee = preg_replace( '|<p>(<li.+?)</p>|', '$1', $pee );

	// If a <blockquote> is wrapped with a <p>, move it inside the <blockquote>.
	$pee = preg_replace( '|<p><blockquote([^>]*)>|i', '<blockquote$1><p>', $pee );
	$pee = str_replace( '</blockquote></p>', '</p></blockquote>', $pee );

	// If an opening or closing block element tag is preceded by an opening <p> tag, remove it.
	$pee = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)!', '$1', $pee );

	// If an opening or closing block element tag is followed by a closing <p> tag, remove it.
	$pee = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $pee );

	// Optionally insert line breaks.
	if ( $br ) {
		// Replace newlines that shouldn't be touched with a placeholder.
		$pee = preg_replace_callback( '/<(script|style).*?<\/\\1>/s', '_autop_newline_preservation_helper', $pee );

		// Normalize <br>
		$pee = str_replace( array( '<br>', '<br/>' ), '<br />', $pee );

		// Replace any new line characters that aren't preceded by a <br /> with a <br />.
		$pee = preg_replace( '|(?<!<br />)\s*\n|', "<br />\n", $pee );

		// Replace newline placeholders with newlines.
		$pee = str_replace( '<WPPreserveNewline />', "\n", $pee );
	}

	// If a <br /> tag is after an opening or closing block tag, remove it.
	$pee = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*<br />!', '$1', $pee );

	// If a <br /> tag is before a subset of opening or closing block tags, remove it.
	$pee = preg_replace( '!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee );
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

	// Replace placeholder <pre> tags with their original content.
	if ( ! empty( $pre_tags ) ) {
		$pee = str_replace( array_keys( $pre_tags ), array_values( $pre_tags ), $pee );
	}

	// Restore newlines in all elements.
	if ( false !== strpos( $pee, '<!-- wpnl -->' ) ) {
		$pee = str_replace( array( ' <!-- wpnl --> ', '<!-- wpnl -->' ), "\n", $pee );
	}

	return $pee;
}

/*
 * A helper function for nl2p.
 */
function wp_replace_in_html_tags( $haystack, $replace_pairs ) {
    // Find all elements.
    $textarr = wp_html_split( $haystack );
    $changed = false;
 
    // Optimize when searching for one item.
    if ( 1 === count( $replace_pairs ) ) {
        // Extract $needle and $replace.
        foreach ( $replace_pairs as $needle => $replace ) {
        }
 
        // Loop through delimiters (elements) only.
        for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
            if ( false !== strpos( $textarr[ $i ], $needle ) ) {
                $textarr[ $i ] = str_replace( $needle, $replace, $textarr[ $i ] );
                $changed       = true;
            }
        }
    } else {
        // Extract all $needles.
        $needles = array_keys( $replace_pairs );
 
        // Loop through delimiters (elements) only.
        for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $textarr[ $i ], $needle ) ) {
                    $textarr[ $i ] = strtr( $textarr[ $i ], $replace_pairs );
                    $changed       = true;
                    // After one strtr() break out of the foreach loop and look at next element.
                    break;
                }
            }
        }
    }
 
    if ( $changed ) {
        $haystack = implode( $textarr );
    }
 
    return $haystack;
}

/*
 * A helper function for nl2p.
 */
function wp_html_split( $input ) {
	return preg_split( get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
}

/*
 * A helper function for nl2p.
 */
function _autop_newline_preservation_helper( $matches ) {
	return str_replace( "\n", '<WPPreserveNewline />', $matches[0] );
}

/*
 * A helper function for nl2p.
 */
function get_html_split_regex() {
	static $regex;

	if ( ! isset( $regex ) ) {
		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
		$comments =
			'!'             // Start of comment, after the <.
			. '(?:'         // Unroll the loop: Consume everything until --> is found.
			.     '-(?!->)' // Dash not followed by end of comment.
			.     '[^\-]*+' // Consume non-dashes.
			. ')*+'         // Loop possessively.
			. '(?:-->)?';   // End of comment. If not found, match all input.

		$cdata =
			'!\[CDATA\['    // Start of comment, after the <.
			. '[^\]]*+'     // Consume non-].
			. '(?:'         // Unroll the loop: Consume everything until ]]> is found.
			.     '](?!]>)' // One ] not followed by end of comment.
			.     '[^\]]*+' // Consume non-].
			. ')*+'         // Loop possessively.
			. '(?:]]>)?';   // End of comment. If not found, match all input.

		$escaped =
			'(?='             // Is the element escaped?
			.    '!--'
			. '|'
			.    '!\[CDATA\['
			. ')'
			. '(?(?=!-)'      // If yes, which type?
			.     $comments
			. '|'
			.     $cdata
			. ')';

		$regex =
			'/('                // Capture the entire match.
			.     '<'           // Find start of element.
			.     '(?'          // Conditional expression follows.
			.         $escaped  // Find end of escaped element.
			.     '|'           // ... else ...
			.         '[^>]*>?' // Find end of normal element.
			.     ')'
			. ')/';
		// phpcs:enable
	}

	return $regex;
}

function login_form()
{
    if (isset($_GET['return_uri']))
    {
        $return_uri = $_GET['return_uri'];
    }
    elseif ( isset($form_data) && isset($form_data['return_uri']) )
    {
        $return_uri = $_GET['return_uri'];
    }
    $returned_data = '
		<form method="post" action="/account/login/">
  
            <fieldset class="login">
                <label for="email">E-Mail Address</label><br>
                <input type="email" size="20" maxlength="60" id="email" name="form_data[email]"><br>
                <label for="password">Password</label><br>
                <input type="password" size="20" maxlength="255" id="password" name="form_data[password]"><br>
                <input type="submit" name="submit" value="Log In" />
            </fieldset>';
    if (isset($return_uri))
    {
        $returned_data .= '
			<input type="hidden" name="form_data[return_uri]" value="' . $return_uri . '" />';
    }
    $returned_data .= '
		</form>';
    return $returned_data;
}


function login_redirect()
{
    header('Location: http://'. $_SERVER['SERVER_NAME'] .'/login/?return_uri=' . $_SERVER['REQUEST_URI']);
    exit();
}


# Create a random string of lowercased letters and numbers of a given length.
function generate_hash($length)
{
    if (empty($length))
    {
        $length = 5;
    }
    // define a corpus of letters and numbers
    $corpus = 'abcdefghijklmnopqrstuvwxyz1234567890';
    $hash = '';

    for ($i=0; $i<$length; $i++)
    {
        $corpus = str_shuffle($corpus);
        $hash .= mb_substr($corpus, 0, 1);
    }
    return $hash;
}

# Create a tag cloud.
function tag_cloud($tags)
{
    if (!isset($tags) || !is_array($tags))
    {
        return FALSE;
    }

    $html = '';

    # Determine if we're going to use a logarithmic or a square root scale for this tag cloud.
    # That's based on the disparity between the smallest and the largest tags.
    if (reset($tags) / end($tags) > 10)
    {
        $scale = 'log';
    }
    else
    {
        $scale = 'sqrt';
    }

    # Establish a scale -- the average size in this list should be 1.25em, with the scale moving
    # up and down from there.
    $multiple = 1.25 / (array_sum($tags) / count($tags));

    # Step through every tag and adjust the size downward, normalizing at 1em.
    foreach ($tags as $tag => &$count)
    {
        $size = round(($count * $multiple), 1);
        if ($size > 4)
        {
            $size = 4;
        }
        elseif ($size < .75)
        {
            $size = .75;
        }

        $html .= '<span style="font-size: ' . $size . 'em;"><a href="/bills/tags/' . urlencode($tag) . '/">' . $tag . '</a></span> ';
    }

    return $html;
}

# As of this writing, the server is running PHP 5.1.8. So here's a function to substitute for
# json_encode, which wasn't added until 5.2, courtesy of Anonymous, found at
# http://www.php.net/manual/en/function.json-decode.php#100740.
if (!function_exists('json_decode'))
{
    function json_decode($json)
    {
        $comment = false;
        $out = '$x=';

        for ($i=0; $i<mb_strlen($json); $i++)
        {
            if (!$comment)
            {
                if (($json[$i] == '{') || ($json[$i] == '['))
                {
                    $out .= ' array(';
                }
                elseif (($json[$i] == '}') || ($json[$i] == ']'))
                {
                    $out .= ')';
                }
                elseif ($json[$i] == ':')
                {
                    $out .= '=>';
                }
                else
                {
                    $out .= $json[$i];
                }
            }
            else
            {
                $out .= $json[$i];
            }
            if ($json[$i] == '"' && $json[($i-1)]!="\\")
            {
                $comment = !$comment;
            }
        }
        eval($out . ';');
        return $x;
    }
}


# Return a listing of all sections of the Code of Virginia that are mentioned in the bill ID in
# question.
function bill_sections($bill_id)
{
    if (!isset($bill_id))
    {
        return FALSE;
    }

    $sql = 'SELECT vacode.section_number, vacode.section_name AS catch_line
			FROM bills_section_numbers
			LEFT JOIN vacode
				ON bills_section_numbers.section_number=vacode.section_number
			WHERE bills_section_numbers.bill_id = ' . $bill_id . '
			AND vacode.section_number IS NOT NULL
			ORDER BY vacode.section_number ASC';

    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) < 1)
    {
        return FALSE;
    }
    else
    {
        while ($section = mysqli_fetch_array($result))
        {
            $section['url'] = 'https://vacode.org/' . $section['section_number'] . '/';
            $sections[] = $section;
        }
    }

    # In case we wound up with no viable sections.
    if (count($section) == 0)
    {
        return FALSE;
    }

    return $sections;
}

# When provided with a URL, Varnish will erase that URL from its cache.
function varnish_purge($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
    curl_exec($ch);
    return true;
}

# This is relied on by usort() in bill-full-text.php.
function sort_by_length($a, $b)
{
    return mb_strlen($b) - mb_strlen($a);
}

# This is used as the preg_replace_callback function that inserts dictionary links into text.
function replace_terms($term)
{
    if (!isset($term))
    {
        return FALSE;
    }

    /*
     * If the provided term is an array of terms, just use the first one. This might seem odd,
     * but note that this function is written to be used within preg_replace_callback(), the
     * PCRE provides an array-based word listing, and we only want the first one.
     */
    if (is_array($term))
    {
        $term = $term[0];
    }

    /*
     * If we have already marked this term as blacklisted -- that is, as a word that is a subset
     * of a longer term -- then just return the term without marking it as a dictionary term.
     */
    if (isset($term_blacklist) && (in_array(mb_strtolower($term), $term_blacklist)))
    {
        return $term;
    }

    /*
     * Determine whether this term is made up of multiple words, so that we can eliminate any
     * terms from our arrays of terms that are any of the individual words that make up this
     * term. That is, if this term is "person or people," and "person" is another term in our
     * array, then we want to drop "person," to avoid display overlapping terms.
     */
    $num_spaces = mb_substr_count($term, ' ');

    if ($num_spaces > 0)
    {

        /*
         * Use that separator to break the term up into an array of words.
         */
        $term_components = explode(' ', $term);

        /*
         * Step through each the the words that make up this phrase, and add each of them to
         * the blacklist, so that we can skip this word next time it appears in this law.
         */
        foreach ($term_components as $word)
        {
            $term_blacklist[] = mb_strtolower($word);
        }

        /*
         * Now step through each two-word sub-phrase that make up this 3+-word phrase (assuming
         * that there are any) and add each of them to the blacklist.
         */
        if ($num_spaces > 1)
        {
            for ($i=0; $i<$num_spaces; $i++)
            {
                $term_blacklist[] = mb_strtolower($term_components[$i] . ' ' . $term_components[$i+1]);
            }
        }
    }

    return '<span class="dictionary">' . $term . '</span>';
}

/**
 * Send an error message formatted as JSON. This requires the text of an error message.
 */
function json_error($text, $status_code='400 OK')
{
    if (!isset($text))
    {
        return FALSE;
    }

    $error = array('error',
        array(
            'message' => 'An Error Occurred',
            'details' => $text
        )
    );
    $error = json_encode($error);

    /*
     * Return a 400 "Bad Request" error. This indicates that the request was invalid. Whether this
     * is the best HTTP header is subject to debate.
     */
    header('HTTP/1.0 ' . $status_code);

    /*
     * Send an HTTP header defining the content as JSON.
     */
    header('Content-type: application/json');
    echo $error;
}

/**
 * Send an alert to the Pushover iOS app.
 */
function pushover_alert($title, $message)
{
    if (!defined('PUSHOVER_KEY') || !isset($title) || !isset($message))
    {
        return FALSE;
    }

    if (mb_strlen($title) > 100)
    {
        $title = mb_substr($title, 0, 100);
    }

    if (mb_strlen($message) > 412)
    {
        $message = mb_substr($message, 0, 412);
    }

    curl_setopt_array($ch = curl_init(), array(
        CURLOPT_URL => "https://api.pushover.net/1/messages.json",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_POSTFIELDS => array(
            "token" => PUSHOVER_KEY,
            "user" => "unBH1CeWWY4F5JL2TzhUodQASDUAUG",
            "title" => $title,
            "message" => $message,
        ),
        CURLOPT_SAFE_UPLOAD => true,
    ));
    curl_exec($ch);
    curl_close($ch);

    return TRUE;
}
