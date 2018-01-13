<?php

###
# Accept Comments
# 
# PURPOSE
# Receives POSTed comments and adds them to the database, determining
# whether they're likely to be spam or require authentication.
# 
# NOTES
# The elements of the array are deliberately given misleading names
# in order to catch spammers.  These are renamed promptly, but incoming
# data will be named oddly.
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
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# LOCALIZE VARIABLES
$comment = $_REQUEST['comment'];

# RENAME VARIABLES
# These had faux names to deter spammers.  Give them proper names.
$comment['name'] = $comment['expiration_date'];
$comment['email'] = $comment['zip'];
$comment['url'] = $comment['age'];

# CHECK FOR SPAMMERS
# If any of these form fields have obviously been filled out based on the
# field name, rather than the label, reject them as spam.  If the bait
# field (state) has been filled out, reject the comment as spam.
if (ereg("([0-9]{2})/([0-9]{2})", $comment['name']))
{
	die();
}
elseif ((strlen($comment['email']) == 5) && (ereg("([0-9]{5})", $comment['email'])))
{
	die();
}
elseif ((strlen($comment['email']) == 5) && (ereg("([0-9]{5})-([0-9]{4})", $comment['email'])))
{
	die();
}
elseif (ereg("([0-9]{2})", $comment['age']))
{
	die();
}
elseif (!empty($comment['state']))
{
	die();
}
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

# See if the user is logged in and, if so, save his user data.
$user = @get_user();

# CLEAN UP THE DATA
$comment = array_map('mysql_escape_string', $comment);
$comment = array_map('trim', $comment);
$comment['comment'] = strip_tags($comment['comment'], '<a><em><strong><i><b><s><blockquote><embed>');

# Validate any provided URL, and silently drop it if it's invalid.
if (!empty($comment['url']))
{
	# If we've got content, but no schema, prepend that.
	if (!stristr($comment['url'], 'http://'))
	{
		# If there's an at sign in there, then somebody has entered their e-mail address as
		# their URL.
		if (strstr($comment['url'], '@'))
		{
			$comment['email'] = $comment['url'];
			unset($comment['url']);
		}
		
		# Otherwise, just figure somebody neglected the prefix.
		else
		{
			$comment['url'] = 'http://'.$comment['url'];
		}
	}	
	
	# If the URL still isn't valid, drop it.
	if (filter_var($comment['url'], FILTER_VALIDATE_URL) === false)
	{
		$comment['url'] = '';
	}
}
if (empty($comment['comment']))
{
	$errors[] .= 'a comment';
}
if (empty($comment['name']))
{
	$errors[] .= 'your name';
}
if (empty($comment['email']))
{
	$errors[] .= 'your e-mail address';
}
if (!validate_email($comment['email']))
{
	$errors[] .= 'a valid e-mail address';
}

# Run the code through HTML Purifier.
// Jeez, I'd love to use HTML Purifier, but it's munging messages. The contents of HREF
// elements are being replaced with garbage characters.
//$purifier = new HTMLPurifier();
//$comment['comment'] = $purifier->purify($comment['comment']);

# SEE IF HE'S LOGGED IN AND DEAL WITH HIM ACCORDINGLY
if (logged_in() === TRUE)
{
	update_user('name='.$comment['name'].'&email='.$comment['email'].'&url='.$comment['url']);
}
else
{
	create_user('name='.$comment['name'].'&email='.$comment['email'].'&url='.$comment['url']);
	$user = @get_user();
}

# If this user is blacklisted, don't let him post.
/*if (@blacklisted() === TRUE)
{
	die();
}*/

# Check the comments against the dirty words.
/*$comment_words = explode(' ', $comment['comment']);
foreach ($comment_words AS $word)
{
	$word = ereg_replace('[[:punct:]]', '', $word);
	$word = strip_tags($word);
	if (in_array($word, $GLOBALS['banned_words']))
	{
		@blacklist($word);
		die();
	}
}*/

# Make sure that this person hasn't posted in the past 5 seconds.
$sql = 'SELECT id
		FROM comments
		WHERE (name="'.$comment['email'].'" OR ip="'.$_SERVER['REMOTE_ADDR'].'")
		AND (TIMESTAMPDIFF(SECOND, date_created, now()) < 5)';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	die('Slow down, cowboy: Only one comment is allowed every five seconds. That’s pretty reasonable.');
}

# Make sure that this person hasn't posted too many times recently.
$sql = 'SELECT *
		FROM comments
		WHERE (name="'.$comment['email'].'" OR ip="'.$_SERVER['REMOTE_ADDR'].'")
		AND (TIMESTAMPDIFF(MINUTE, date_created, now()) < 5)';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 10)
{
	die('Slow down, cowboy: You’re posting way too many comments too fast. Relax, think, then write.');
}

# Make sure that this person hasn't posted this precise same comment within the past hour.
$sql = 'SELECT id
		FROM comments
		WHERE (name="'.$comment['email'].'" OR ip="'.$_SERVER['REMOTE_ADDR'].'")
		AND (TIMESTAMPDIFF(MINUTE, date_created, now()) < 60)
		AND comment="'.$comment['comment'].'"';
$result = mysql_query($sql);
if (mysql_num_rows($result) > 0)
{
	die('You’ve already posted that exact comment. You may not post it again. And, no, don’t '
		. 'just change it a little bit and repost it—a moderator will just delete it. If you’re '
		. 'trying to post the same comment on identical bills, we’ve saved you the trouble. Within '
		. 'an hour, your prior comment will show up on every identical bill, automatically. (You’re '
		. 'welcome!)'
	);
}

if (!isset($errors))
{

	# ASSEMBLE THE INSERTION SQL
	$sql = 'INSERT INTO comments
			SET bill_id='.$comment['bill_id'].', name="'.$comment['name'].'",
			email="'.$comment['email'].'", ip="'.$_SERVER['REMOTE_ADDR'].'",
			comment="'.$comment['comment'].'", status="published",
			date_created=now()';
	if (!empty($comment['url']))
	{
		$sql .= ', url="'.$comment['url'].'"';
	}
	if (!empty($user['id']))
	{
		$sql .= ', user_id='.$user['id'];
	}
	$result = mysql_query($sql);
	if (!$result)
	{
		die('Your comment could not be added, though for no good reason. Richmond Sunlight has
			been alerted to the problem, and somebody will fix this and get back to you. Sorry
			for the trouble!');
		mail('waldo@jaquith.org', 'RS: Comment Submission Failed',
			'A comment submission failed in a way that left the user with an ugly error '
			.'message. These are the contents of the comment array:'."\n\n"
			.print_r($comment, true));
	}
	
	# If this thread has subscribers, e-mail a this comment to those subscribers.
	
	# Create a new instance of the comments-subscription class
	$subscription = new CommentSubscription;
	
	# Pass this bill's ID to $subscription, and have it return a listing of subscriptions
	# to the discussion of this bill (if any).
	$subscription->bill_id = $comment['bill_id'];
	$subscriptions = $subscription->listing();
	
	# If there are any subscriptions to this discussion, we want to send an e-mail to those
	# subscribers.
	if ($subscriptions !== FALSE)
	{

		# Pass the comment data to $subscriptions, first removing the HTML and any slashes.
		$comment['name'] = strip_tags(stripslashes($comment['name']));
		$comment['comment'] = strip_tags(stripslashes($comment['comment']));
		$subscription->comment = $comment;
		
		# And pass the subscription data to $subscriptions.
		$subscription->subscriptions = $subscriptions;
		
		# Finally, send out the e-mails.
		$subscription->send_email();
		
	}
	
	# If the user has asked to be subscribed to this bill's comments, do so.
	if (isset($comment['subscribe']) && ($comment['subscribe'] == 'y'))
	{
		# create a new instance of the comments-subscription class
		$subscription = new CommentSubscription;
		
		# pass the user ID and the bill ID
		$subscription->user_id = $user['id'];
		$subscription->bill_id = $comment['bill_id'];
		
		# subscribe this person
		$subscription->save();
	}
	
	/*
	 * Clear the Memcached cache for comments on this bill.
	 */
	$mc = new Memcached();
	$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
	$comments = $mc->delete('comments-' . $comment['bill_id']);
	
	$log = new Log;
	$log->put('New comment posted, by ' . stripslashes($comment['name']) . '.'
		. "\n\n" . stripslashes($comment['comment'])
		. ' https://' . $_SERVER['SERVER_NAME'] . $comment['return_to']. '#comments', 3);

	# Redirect the user back to the page of origin.
	if (!empty($comment['return_to']))
	{
		header('Location: https://' . $_SERVER['SERVER_NAME'] . $comment['return_to']);
		exit;
	}
	else
	{
		header('Location: https://' . $_SERVER['SERVER_NAME']);
		exit;
	}
	
}

else
{

	# PAGE METADATA
	$page_title = 'Comment Verification';
	
	$page_body = '<div class="error">
		<p>You must provide:</p>
		<ul>
			<li>'.implode('</li><li>', $errors).'</li>
		</ul>
	</div>';
	
	# OUTPUT THE PAGE
	$page = new Page;
	$page->page_title = $page_title;
	$page->page_body = $page_body;
	$page->page_sidebar = $page_sidebar;
	$page->process();
	
}
