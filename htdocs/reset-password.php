<?php

###
# Reset Password
# 
# PURPOSE
# Allows a user to reset his password.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
require_once('includes/functions.inc.php');
require_once('includes/settings.inc.php');
require_once('includes/phpmailer/class.phpmailer.php');
include_once('vendor/autoload.php');

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# PAGE METADATA
$page_title = 'Reset Your Password';
$site_section = '';

# INITIALIZE SESSION
session_start();

# If we're receiving a request for a reset -- that is, somebody actually clicking on a
# link in an e-mail after completing the first half of the process.
if (!empty($_GET['hash']))
{
	$hash = mysql_real_escape_string($_GET['hash']);
	$sql = 'SELECT cookie_hash
			FROM users
			WHERE private_hash = "' . $hash . '"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0)
	{
		die('Your password reset link has failed mysteriously.');
	}
	$user_data = mysql_fetch_array($result);
	$_SESSION['id'] = $user_data['cookie_hash'];
	header('Location: https://www.richmondsunlight.com/account/?reset');
	exit();
}

if (!empty($_POST['email']))
{
	$email = $_POST['email'];
	if (!validate_email($email)) $error = 'That’s not a valid e-mail address.';
	
	# If there are no errors so far, check the database.
	if (!isset($error))
	{

		$sql = 'SELECT name, email, private_hash
				FROM users
				WHERE private_hash IS NOT NULL AND password IS NOT NULL
				AND email = "'.mysql_real_escape_string($email).'"';
		$result = mysql_query($sql);
		
		# If we find nothing.
		if (mysql_num_rows($result) == 0)
		{
			$error = 'You don’t have an account on Richmond Sunlight under that e-mail address.';
		}
		else
		{

			$user_data = mysql_fetch_array($result);
			
			$user_data = array_map('stripslashes', $user_data);
			
			# Assemble the e-mail body.
			$email_body = $user_data['name'] . ",\n\n" .
				'As you requested, here is a link to a page where you can reset your password '.
				"on Richmond Sunlight.\n\n" .
				'http://www.richmondsunlight.com/account/reset-password/' . $user_data['private_hash'] . "\n\n" .
				'If you didn\'t request that your password be reset, don\'t worry -- you can just ' .
				'ignore this e-mail. No harm done.' . "\n\n" .
				"Best wishes,\nRichmond Sunlight";
			
			# Send the e-mail using PHP Mailer.
			$mail = new PHPMailer();
			$mail->From = 'do_not_reply@richmondsunlight.com';
			$mail->FromName = 'Richmond Sunlight';
			$mail->AddAddress($user_data['email'], $user_data['name']);
			$mail->Body = stripslashes($email_body);
			$mail->Subject = 'Password Reset';
			$mail->IsHTML(false);
			$mail->Send();
			
			$page_body = '
				<div id="messages" class="updated">
					<p>An e-mail has been sent to you at that address. Check your e-mail and click
					on the link provided and you’ll be in business.</p>
				</div>';
			
		}
	}
	
	if (isset($error))
	{
		$page_body = '
			<div id="messages" class="errors">
				<p>' . $error . '</p>
			</div>';
	}
}

# Display the password reset form.
$page_body .= '
	<p>Forgot your password? No problem. Enter your e-mail address here and we’ll e-mail you
	a link so you can reset it.</p>

	<form method="post" action="/account/reset-password/">
		<label for="email">Your E-Mail Address</label><br />
		<input type="text" name="email" size="32" id="email" maxlength="64" /><br />
		<input type="submit" name="submit" id="submit" value="Reset Password" />
	</form>
';

# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
