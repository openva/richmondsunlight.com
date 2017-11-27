<?php

###
# Accept Tag Additions
# 
# PURPOSE
# Receives submitted tags, adds them, and returns the user back to the
# bill page.
# 
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once('settings.inc.php');
include_once('functions.inc.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
connect_to_db();

# INITIALIZE SESSION
session_start();

# Grab the user data.
$user = get_user();

# LOCALIZE VARIABLES
$tags = $_REQUEST['tags'];
if (isset($_REQUEST['delete']))
{
	$delete = $_REQUEST['delete'];
}
if (isset($_REQUEST['bill_id']))
{
	$bill_id = $_REQUEST['bill_id'];
}

# REJECT MISSING TAGS
if (empty($tags['tags']))
{
	# But if the tags are missing because a trusted user is deleting one, that's fine.
	if (!empty($delete) && ($user['trusted'] == 'y'))
	{
		
		# Delete the tag.
		$sql = 'DELETE FROM tags
				WHERE id=' . $delete;
		mysql_query($sql);
		
		# Delete the bill from Memcached.
		$mc = new Memcached();
		$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		$result = $mc->delete('bill-' . $bill_id);
		
		$tmp = parse_url($_SERVER['HTTP_REFERER']);
		$return_to = $tmp['path'];
		
		header('Location: '.$return_to);
		exit;
	}
	
	header('Location: https://' . $_SERVER['SERVER_NAME'] . $tags['return_to']);
	exit;
}

# BAR SPAMMERS
if (strlen($_SERVER['HTTP_USER_AGENT']) <= 1)
{
	die('Thank you for your comment.');
}
elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'curl') === TRUE)
{
	die('Thank you for your comment.');
}
elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'Wget') === TRUE)
{
	die('Thank you for your comment.');
}

# Log the user in.
if (!logged_in())
{
	create_user();
}

if ((!empty($_SESSION['id'])))// && !blacklisted())
{
	
	# Explode the tags into an array to be inserted individually.
	$tag = explode(',', $tags['tags']);
	
	for ($i=0; $i<count($tag); $i++)
	{

		$tag[$i] = strtolower(trim($tag[$i]));
		
		# Check the tag against the dirty words.
		/*if (in_array($tag[$i], $GLOBALS['banned_words']))
		{
			@blacklist($tag[$i]);
			break;
		}*/
		
		# Drop useless tags.
		if ($tag[$i] === '1')
		{
			continue;
		}
		
		# Don't proceed if it's blank.
		if (!empty($tag[$i]))
		{
		
			# Make sure it's safe.
			$tag[$i] = ereg_replace("[[:punct:]]", '', $tag[$i]);
			$tag[$i] = trim(mysql_real_escape_string($tag[$i]));
			
			# Check one more time to make sure it's not empty.
			if (!empty($tag[$i]))
			{
				# Assemble the insertion SQL
				$sql = 'INSERT INTO tags
						SET bill_id=' . $tags['bill_id'] . ', tag="' . $tag[$i] . '",
						ip="' . $_SERVER['REMOTE_ADDR'] . '", user_id=
							(SELECT id
							FROM users
							WHERE cookie_hash = "' . $_SESSION['id'] . '"),
						date_created=now()';
				$result = mysql_query($sql);
			}
			
		}
	}
	
	# If the insert was successful
	if (!empty($tags['return_to']))
	{
		
		# Delete the bill from Memcached.
		$mc = new Memcached();
		$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
		$result = $mc->delete('bill-' . $tags['bill_id']);
	
		$tag_list = implode(', ', $tag);
		$log = new Log;
		$result = $log->put('New tags added: ' . $tag_list . ' https://' . $_SERVER['SERVER_NAME'] . $tags['return_to'], 3);
		
		# Redirect the user back to the page of origin.
		header('Location: https://' . $_SERVER['SERVER_NAME'] . $tags['return_to']);
		exit;
		
	}
}

# If the user didn't accept a cookie or is blacklisted.
else
{
	header('Location: https://' . $_SERVER['SERVER_NAME'] . $tags['return_to']);
	exit;
}
