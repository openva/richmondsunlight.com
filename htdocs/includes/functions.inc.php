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
# !!! See also composer.json in the webroot!
function __autoload_libraries($name)
{
	
	if (file_exists($_SERVER['DOCUMENT_ROOT'] . 'includes/class.' . $name . '.php') === TRUE)
	{	
		include 'class.' . $name . '.php';
		return TRUE;
	}
	
	return;
	
}

spl_autoload_register('__autoload_libraries');

# Cache data. Here we're using the original homebrewed caching function as a wrapper for
# its replacement, PEAR::Cache_Lite.
function cache_save($data, $key)
{
	
	# If a cache key hasn't been specified, assume it's the current page.
	if (!isset($key) || empty($key))
	{
		$key = md5($_SERVER['REQUEST_URI']);
	}

	/*
	 * Connect to Memcached.
	 */
	$mc = new Memcached();
	$mc->addServer("127.0.0.1", 6379);
			
	/*
	 * Cache this page in Memcached for one hour.
	 */
	$mc->set('page-' . $key, $data, 3600);
	
	return TRUE;
	
}

# Retrieve data from the cache
function cache_open($key)
{
	
	# If a cache key hasn't been specified, assume it's the current page.
	if (!isset($key))
	{
		$key = md5($_SERVER['REQUEST_URI']);
	}

	/*
	 * Connect to Memcached.
	 */
	$mc = new Memcached();
	$mc->addServer("127.0.0.1", 6379);
	
	/*
	 * If this page is cached in Memcached, retrieve it from there.
	 */
	$result = $mc->get('page-' . $key);
	if ($result !== FALSE)
	{
		return $result;
	}
	else
	{
		return false;
	}
	
}
	
# Delete data from the cache
function cache_delete($key)
{
	
	# If a cache file name hasn't been specified, assume it's the current page.
	if (!isset($key))
	{
		$key = md5($_SERVER['REQUEST_URI']);
	}
	
	# Delete the cache file.
	$sql = 'DELETE FROM cache
			WHERE key="' . $key . '"';
	$result = mysql_query($sql);
	
	return true;
	
}
	
# Return the age of a specific cache record.
function cache_age($key)
{
	
	# If a cache record name hasn't been specified, assume it's the current page.
	if (!isset($key))
	{
		$key = md5($_SERVER['REQUEST_URI']);
	}
	
	# Select the age from the database.
	$sql = 'SELECT now() - date_created AS age
			FROM cache
			WHERE key="' . $key . '"';
	$result = mysql_query($sql);
	if ($result === false)
	{
		return false;
	}
	$cache = mysql_fetch_array($result);
	
	return $cache['age'];
	
}
	
# Determine whether a cache file exists.
function cache_exists($key)
{

	# If a cache file name hasn't been specified, assume it's the current page.
	if (!isset($key))
	{
		$key = md5($_SERVER['REQUEST_URI']);
	}
	
	# See if the record is in the database.
	$sql = 'SELECT *
			FROM cache
			WHERE key="' . $key . '"';
	$result = mysql_query($sql);
	
	# We can return the result directly.
	return $result;
	
}
	
# Connect to the database
function connect_to_db($type = 'old')
{
	
	if ($type == 'old')
	{
		$db = mysql_connect(PDO_SERVER, PDO_USERNAME, PDO_PASSWORD);
		if ($db === FALSE)
		{
			header('Location: https://www.richmondsunlight.com/site-down/');
			exit;
		}
		mysql_select_db('richmondsunlight',$db);
		mysql_query('SET NAMES "utf8"');
	}
	
	elseif ($type == 'pdo')
	{
	
		$db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);
		if ($db === FALSE)
		{
			header('Location: https://www.richmondsunlight.com/site-down/');
			exit;
		}
		return $db;
	
	}
	
	return TRUE;
	
}



# Makes sure that an e-mail address has a valid format.
function validate_email($email)
{
	
	if (empty($email))
	{
		return false;
	}
	
	if (filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		return true;
	}
	return false;
	
}
	
	

# Pivots text around a comma.  God knows what it would do if there was more than one comma. Good
# for lastname, firstname type things.  If there is no comma, it just spits the text back out, happy
# as can be.  If there's no text, then it returns false.
function pivot($text)
{
	
	if (empty($text))
	{
		return false;
	}
	if (strpos($text, ', ') !== false)
	{
		$str = explode(",", $text);
		$text = $str[1].' '.$str[0];
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
		$returned_data .= 'value="'.$q.'" ';
	}
	$returned_data .= '/> <input type="submit" value="Search" class="submit" />';
	return $returned_data;
}

# Spam-proof an e-mail address.
function spam_proof($email)
{
	if (empty($email))
	{
		return false;
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
			$returned_data = $seconds.' second';
		}
		elseif ($seconds < 3600)
		{
			$returned_data = round($seconds / 60).' minute';
		}
		elseif ($seconds < 86400)
		{
			$returned_data = round($seconds / 60 / 60).' hour';
		}
		elseif ($seconds < 2592000)
		{
			$returned_data = round($seconds / 60 / 60 / 24).' day';
		}
		elseif ($seconds >= 2592000)
		{
			$returned_data = round($seconds / 60 / 60 / 24 / 30).' month';
		}
		
		if (substr($returned_data, 0, 2) != '1 ')
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
	list($h,$m,$s) = explode(':', $time);
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
					$value = mysql_real_escape_string($value);
					if (empty($value))
					{
						$sql_inserts .= ', '.$key.' = NULL';
					}
					else
					{
						$sql_inserts .= ', '.$key.' = "'.$value.'"';
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
					$value = mysql_real_escape_string($value);
					
					# Determine which SQL string this data should be appended to.
					if (($key == 'organization') || ($key == 'type') || ($key == 'expires'))
					{
						$dashboard_inserts .= ', '.$key.' = "'.$value.'"';
					}
					elseif ($key == 'dashboard')
					{
						sleep(0);
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
								$users_inserts .= ', '.$key.' = MD5("'.$value.'")';
							}
						}
						else
						{
							$users_inserts .= ', '.$key.' = "'.$value.'"';
						}
					}
				}
				
			}
		}
		
		# Generate a session ID -- the cookie hash.
		$_SESSION['id'] = create_session_id();
		
		# Insert the user data.
		$sql = 'INSERT INTO users
				SET cookie_hash="'.$_SESSION['id'].'",
				ip="'.$_SERVER['REMOTE_ADDR'].'", date_created=now()';
		if (!empty($users_inserts))
		{
			$sql .= $users_inserts;
		}
		if (!empty($sql_inserts))
		{
			$sql .= $sql_inserts;
		}
		$result = @mysql_query($sql);
		if (!$result)
		{
			return false;
		}
		elseif ($options['dashboard'] == 'y')
		{
			
			# Get the user's ID.
			$user_id = mysql_insert_id();
						
			# Generate a random eight-digit hash to send out in e-mails for unsubscribing
			# instantly.
			$chars = 'bcdfghjklmnpqrstvxyz0123456789';
			$hash = substr(str_shuffle($chars), 0, 8);
			
			# Insert the Dashboard user data.
			$sql = 'INSERT INTO dashboard_user_data
				SET user_id = '.$user_id.', email_active="y", last_access=now(),
				date_created=now(), unsub_hash="'.$hash.'"';
			if (!empty($dashboard_inserts)) $sql .= $dashboard_inserts;
			mysql_query($sql);
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
	
	$sql = 'SELECT users.id, users.name, users.password, users.email, users.url,
			users.zip, users.city, users.state, users.house_district_id, users.senate_district_id,
			users.latitude, users.longitude, users.trusted, users.ip, users.date_modified,
			users.date_created, users.private_hash, dashboard_user_data.organization,
			dashboard_user_data.type, dashboard_user_data.last_access
			FROM users
			LEFT JOIN dashboard_user_data
				ON users.id=dashboard_user_data.user_id
			WHERE users.cookie_hash="'.mysql_real_escape_string($_SESSION['id']).'"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{
		return FALSE;
	}
	$user = mysql_fetch_array($result, MYSQL_ASSOC);
	$user = array_map('stripslashes', $user);
	
	# Cache this user's data, and save it for one hour. (User sessions are unlikely to last longer.)
	$mc->set( 'user-' . $_SESSION['id'], $user, (60 * 60) );
	
	return $user;
	
}
	
# Update a user's data.
function update_user($options)
{
	parse_str($options, $options);
	if (count($options) < 1)
	{
		return false;
	}
	if (empty($_SESSION['id']))
	{
		return false;
	}
	
	# If this user's data is cached in APC, delete it, since it's now out of date.
	if (apc_exists('user-'.$_SESSION['id']) !== false)
	{
		apc_delete('user-'.$_SESSION['id']);
	}
	
	# Assemble the SQL string.
	$sql = 'UPDATE users SET ';
	$first = 'yes';
	foreach ($options as $key => $value)
	{
		mysql_real_escape_string($value);
		if (!isset($first))
		{
			$sql .= ', ';
		}
		else
		{
			unset($first);
		}
		$sql .= $key.' = "'.$value.'"';
	}
	$sql .= ' WHERE cookie_hash="'.mysql_real_escape_string($_SESSION['id']).'"';
	$result = mysql_query($sql);
	if (!$result)
	{
		return false;
	}
	return true;
}
	
# Generate a random session ID.
function create_session_id()
{
	return md5(uniqid(rand(1000000,9999999), true));
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
		return false;
	}
	
	/*
	 * If this session ID is stored in Memcached, then we don't need to query the database.
	 */	 
	$mc = new Memcached();
	$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
	$result = $mc->get('user-session-' . $_SESSION['id']);
	if ($mc->getResultCode() === 0)
	{
		return TRUE;
	}
	
	/*
	 * If this is a registered visitor (as opposed to somebody who has posted comments,
	 * voted, etc., but hasn't created an account).
	 */
	if ($registered == 'registered')
	{
		$sql = 'SELECT id
				FROM users
				WHERE cookie_hash="' . mysql_real_escape_string($_SESSION['id']) . '"
				AND password IS NOT NULL';
	}
	
	/*
	 * If this is not a registered user.
	 */
	else
	{	
		$sql = 'SELECT id
				FROM users
				WHERE cookie_hash="' . mysql_real_escape_string($_SESSION['id']) . '"';
	}
	$result = mysql_query($sql);
	
	/*
	 * If one result is returned, then this user does have an account.
	 */
	if (mysql_num_rows($result) == 1)
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
		$mc->set( 'user-session-' . $_SESSION['id'], $is_registered, (60 * 30) );
		return true;
	}
	
	return false;
	
}
	
# Determine whether the current user is blacklisted from participating, by looking at his cumulative
# score and, if it's 20 or greater, prevent him from participating.
function blacklisted()
{
	$sql = 'SELECT SUM(score) AS score
			FROM blacklist
			WHERE ip="'.$_SERVER['REMOTE_ADDR'].'" OR user_id =
				(SELECT id
				FROM users
				WHERE cookie_hash = "'.$_SESSION['id'].'")';
	$result = mysql_query($sql);
	$data = mysql_fetch_array($result);
	$score = $data['score'];
	if ($score >= 20)
	{
		return true;
	}
	else
	{
		return false;
	}
}
	
# Add the current user to the blacklist.
function blacklist($word)
{
	
	$sql = 'INSERT INTO blacklist
			SET ip="' . $_SERVER['REMOTE_ADDR'] . '", user_id = 
				(SELECT id
				FROM users
				WHERE cookie_hash = "'.$_SESSION['id'].'"),
			date_created=now(),
			score=20';
	if (isset($word))
	{
		$sql .= ', reason="dirty word - ' . $word . '"';
	}
	
	mysql_query($sql);
	
}

# Determine whether the current user has voted on this poll before.
function has_voted($bill_id)
{
	if (empty($bill_id))
	{
		return FALSE;
	}
	if (logged_in() === FALSE)
	{
		return FALSE;
	}
	else
	{
		$sql = 'SELECT *
				FROM polls
				WHERE user_id=
					(SELECT id
					FROM users
					WHERE cookie_hash = "' . $_SESSION['id'] . '")
				AND bill_id=' . $bill_id;
		$result = mysql_query($sql);
		if (mysql_num_rows($result) > 0)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
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
	if (empty($status)) return false;
	if ($status == 'continued') return 'Continued to Next Session';
	elseif ($status == 'introduced') return 'Introduced';
	elseif ($status == 'committee') return 'In Committee';
	elseif ($status == 'in committee') return 'In Committee';
	elseif ($status == 'in subcommittee') return 'In Subcommittee';
	elseif ($status == 'failed subcommittee') return 'Subcommittee Recommends Killing the Bill';
	elseif ($status == 'passed subcommittee') return 'Subcommittee Recommends Passing the Bill';
	elseif ($status == 'failed committee') return 'Failed to Pass in Committee';
	elseif ($status == 'stricken') return 'Bill Killed at Sponsor’s Request';
	elseif ($status == 'passed house') return 'Passed the House';
	elseif ($status == 'passed senate') return 'Passed the Senate';
	elseif ($status == 'passed') return 'Passed the General Assembly';
	elseif ($status == 'failed') return 'Failed to Advance';
	elseif ($status == 'approved') return 'Signed into Law';
	elseif ($status == 'incorporated') return 'Incorporated into Another Bill';
	else return $status;
}

# A simple wrapper for CURL.
function get_content($url)
{
	$ch = curl_init();
	
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_HEADER, 0);
	
	ob_start();
	
	curl_exec ($ch);
	curl_close ($ch);
	$string = ob_get_contents();
	
	ob_end_clean();
	
	if (empty($string))
	{
		return false;
	}
	
	return $string;
}

# Given a district number and a chamber, returns its database ID.
function district_to_id($number, $chamber)
{
	if (!isset($number) || !isset($chamber))
	{
		return false;
	}
	
	# Select the information from the database.
	$sql = 'SELECT id
			FROM districts
			WHERE number = '.$number.' AND chamber = "'.$chamber.'"
			AND date_ended IS NULL';
	$result = mysql_query($sql);
	
	# Continue if we've got a match.
	if (mysql_num_rows($result) > 0)
	{	
		# Return the database ID.
		$district = mysql_fetch_array($result);
		return $district['id'];
	}
	
	return false;
}

# nl2p is WordPress' wpautop(), renamed
function nl2p($pee, $br = 1)
{
	$pee = $pee . "\n"; // just to make things a little easier, pad the end
	$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
	// Space things out a little
	$allblocks = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|address|math|style|script|object|input|param|p|h[1-6])';
	$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
	$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
	$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
	$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
	$pee = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $pee); // make paragraphs, including one at the end
	$pee = preg_replace('|<p>\s*?</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
	$pee = preg_replace( '|<p>(<div[^>]*>\s*)|', "$1<p>", $pee );
	$pee = preg_replace('!<p>([^<]+)\s*?(</(?:div|address|form)[^>]*>)!', "<p>$1</p>$2", $pee);
	$pee = preg_replace( '|<p>|', "$1<p>", $pee );
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
	$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
	$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
	$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
	$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
	if ($br)
	{
		$pee = preg_replace('/<(script|style).*?<\/\\1>/se', 'str_replace("\n", "<WPPreserveNewline />", "\\0")', $pee);
		$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
		$pee = str_replace('<WPPreserveNewline />', "\n", $pee);
	}
	$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
	$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
	if (strstr($pee, '<pre'))
	{
		$pee = preg_replace('!(<pre.*?>)(.*?)</pre>!ise', " stripslashes('$1') .  stripslashes(clean_pre('$2'))  . '</pre>' ", $pee);
	}
	$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

	return $pee;
}

function login_form()
{

	if (isset($_GET['return_uri'])) $return_uri = $_GET['return_uri'];
	elseif (isset($form_data['return_uri'])) $return_uri = $_GET['return_uri'];
	$returned_data = '
		<form method="post" action="/account/login/">
			
			<table class="form">
				<tr><td><label for="name">E-Mail Address</label></td></tr>
				<tr><td><input type="email" size="20" maxlength="60" id="email" name="form_data[email]" /></td></tr>
				<tr><td><label for="name">Password</label></td></tr>
				<tr><td><input type="password" size="20" maxlength="30" id="password" name="form_data[password]" /></td></tr>
				<tr><td><input type="submit" name="submit" value="Log In" /></td></tr>
			</table>';
	if (isset($return_uri)) $returned_data .= '
			<input type="hidden" name="form_data[return_uri]" value="'.$return_uri.'" />';
	$returned_data .= '
		</form>';
	return $returned_data;
	
}


function login_redirect()
{
	header('Location: http://www.richmondsunlight.com/login/?return_uri='.$_SERVER['REQUEST_URI']);
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
		$hash .= substr($corpus, 0, 1);
	}
	return $hash;
}

# Create a tag cloud.
function tag_cloud($tags)
{

	if ( !isset($tags) || !is_array($tags) )
	{
		return false;
	}
	
	$html = '';
	
	# Determine if we're going to use a logarithmic or a square root scale for this tag cloud.
	# That's based on the disparity between the smallest and the largest tags.
	if ( reset($tags) / end($tags) > 10 )
	{
		$scale = 'log';
	}
	else
	{
		$scale = 'sqrt';
	}
	
	# Establish a scale -- the average size in this list should be 1.25em, with the scale moving
	# up and down from there.
	$multiple = 1.25 / ( array_sum($tags) / count($tags) );
	
	# Step through every tag and adjust the size downward, normalizing at 1em.
	foreach ($tags AS $tag => &$count)
	{
		
		$size = round( ($count * $multiple), 1);
		if ($size > 4)
		{
			$size = 4;
		}
		elseif ($size < .75)
		{
			$size = .75;
		}
		
		$html .= '<span style="font-size: '.$size.'em;"><a href="/bills/tags/'.urlencode($tag).'/">'.$tag.'</a></span> ';
		
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
	  
		for ($i=0; $i<strlen($json); $i++)
		{
			if (!$comment)
			{
				if (($json[$i] == '{') || ($json[$i] == '[')) $out .= ' array(';
				else if (($json[$i] == '}') || ($json[$i] == ']')) $out .= ')';
				else if ($json[$i] == ':') $out .= '=>';
				else $out .= $json[$i];          
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
		return false;
	}
	
	$sql = 'SELECT vacode.section_number, vacode.section_name AS catch_line
			FROM bills_section_numbers
			LEFT JOIN vacode
				ON bills_section_numbers.section_number=vacode.section_number
			WHERE bills_section_numbers.bill_id = ' . $bill_id . '
			AND vacode.section_number IS NOT NULL
			ORDER BY vacode.section_number ASC';
	
	$result = mysql_query($sql);
	if (mysql_num_rows($result) < 1)
	{
		return false;
	}
	else
	{
		while ($section = mysql_fetch_array($result))
		{
			$section['url'] = 'https://vacode.org/'.$section['section_number'].'/';
			$sections[] = $section;
		}
	}
	
	# In case we wound up with no viable sections.
	if (count($section) == 0)
	{
		return false;
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
	return strlen($b) - strlen($a);
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
	if ( isset($term_blacklist) && (in_array(strtolower($term), $term_blacklist)) )
	{
		return $term;
	}

	/*
	 * Determine whether this term is made up of multiple words, so that we can eliminate any
	 * terms from our arrays of terms that are any of the individual words that make up this
	 * term. That is, if this term is "person or people," and "person" is another term in our
	 * array, then we want to drop "person," to avoid display overlapping terms.
	 */
	$num_spaces = substr_count($term, ' ');

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
			$term_blacklist[] = strtolower($word);
		}

		/*
		 * Now step through each two-word sub-phrase that make up this 3+-word phrase (assuming
		 * that there are any) and add each of them to the blacklist.
		 */
		if ($num_spaces > 1)
		{
			for ($i=0; $i<$num_spaces; $i++)
			{
				$term_blacklist[] = strtolower($term_components[$i].' '.$term_components[$i+1]);
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
	
	if ( !defined('PUSHOVER_KEY') || !isset($title) || !isset($message) )
	{
		return FALSE;
	}
	
	if (strlen($title) > 100)
	{
		$title = substr($title, 0, 100);
	}
	
	if (strlen($message) > 412)
	{
		$message = substr($message, 0, 412);
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
