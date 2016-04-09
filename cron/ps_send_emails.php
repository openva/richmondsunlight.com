<?php

	###
	# Send Photosynthesis Bill Update E-Mails
	# 
	# PURPOSE
	# Generates a per-user listing of all bills that were advanced in the period in
	# question (either the past hour or the past day).
	# 
	# TODO
	# * Set the period option to work. It does nothing right now -- all of the code defaults to
	#   daily.
	# * Add an unsubscribe footer.
	#
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	require_once '../includes/settings.inc.php';
	require_once '../includes/functions.inc.php';
	require_once '../includes/photosynthesis.inc.php';
	require_once '../includes/phpmailer/class.phpmailer.php';

	# Don't bother to run this if the General Assembly isn't in session.
	if (IN_SESSION == 'n') exit();

	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.
	@connect_to_db();
	
	# LOCALIZE AND CLEAN UP VARIABLES
	$period = $_REQUEST['period'];
	if (($period != 'hourly') && ($period != 'daily'))
	{
		die('No period specified');
	}
	if ($period == 'daily')
	{
		$today = date('Y-m-d');
	}
	

	# THE MAIN PAGE
	
	# Generate a list of every bill that has been advanced within this period.
	$sql = 'SELECT bills.id, bills.number, bills.catch_line, bills_status.status,
			bills_status.translation
			FROM bills_status LEFT JOIN bills ON bills_status.bill_id = bills.id
			WHERE bills_status.session_id=4 AND bills_status.date =  "'.$today.'"
			ORDER by bills.number ASC, bills_status.date_created ASC, bills_status.id ASC';
	$result = @mysql_query($sql);
	
	# If nothing has happened within this period -- as will happen ~half of the time --
	# simply stop processing.
	if (@mysql_num_rows($result) == 0)
	{
		exit('No actions were found in this period.');
	}

	# Store the actions in an array indexed by bill ID.
	while ($status = @mysql_fetch_array($result))
	{
		$status = array_map('stripslashes', $status);
		$action[$status{id}][] = $status;
	}


	# Step through every current paid PS member who is tracking any bills in portfolio w/ e-mail
	# updates on this frequency, and currently has updates enabled. Also include a list of every
	# bill ID that the member wants updates on.
	$sql = 'SELECT DISTINCT dashboard_portfolios.user_id, users.name, users.email,
				(SELECT GROUP_CONCAT(bill_id)
				FROM dashboard_bills LEFT JOIN dashboard_portfolios
				ON dashboard_bills.portfolio_id = dashboard_portfolios.id
				WHERE dashboard_bills.user_id = users.id
				AND dashboard_portfolios.notify = "daily") AS bills,
			dashboard_user_data.unsub_hash
			FROM users LEFT JOIN dashboard_portfolios
			ON users.id = dashboard_portfolios.user_id
			LEFT JOIN dashboard_user_data
			ON dashboard_portfolios.user_id = dashboard_user_data.user_id
			WHERE dashboard_user_data.email_active = "y" AND dashboard_user_data.type="paid"
			AND (dashboard_user_data.expires > now() OR dashboard_user_data.expires IS NULL)
			HAVING bills IS NOT NULL';
	$result = @mysql_query($sql);
	
	# If no paid users are tracking any bills (it could happen), then simply stop processing.
	if (@mysql_num_rows($result) == 0)
	{
		exit('No paid users are tracking any bills.');
	}
	
	# Step through each user.
	while ($user = @mysql_fetch_array($result))
	{
		$user = array_map('stripslashes', $user);
		$user['bills'] = explode(',', $user['bills']);
				
		# Drop every bill from this user's array that hasn't undergone any status changes.
		foreach ($user['bills'] AS $key => &$bill)
		{
			if (!isset($action[$bill]))
			{
				unset($user['bills'][$key]);
			}
		}
		
		# Reindex the array to compensate for having dropped so many elements from the array.
		$user['bills'] = array_values($user['bills']);
		
		# If after dropping those bills the user doesn't have any bills remaining, then skip him
		# and move onto the next user.
		if (count($user['bills']) == 0)
		{
			continue;
		}
		
		# Establish the body of the e-mail.
		$email_body = '';
		
		foreach ($user['bills'] AS $bill)
		{
			
			# Set the e-mail subject.
			$email_subject = 'Updates to '.count($user['bills']).' Bills';
			
			$email_body .= $action[$bill][0]['number'].': '.$action[$bill][0]['catch_line']."\r";
			foreach($action[$bill] AS $status)
			{
				$email_body .= '* '.$status['status']."\r";
			}
			$email_body .= "\r";
		}
		
		$email_body .= "\r\r----------\r".
			'Manage Your Photosynthesis Settings'."\r".
			'http://www.richmondsunlight.com/photosynthesis/';/*."\r".
			'Unsubscribe Instantly'."\r".
			'http://www.richmondsunlight.com/photosynthesis/unsubscribe/'.$user['unsub_hash'].'/';*/
			
		# Send the e-mail using PHP Mailer.
		$mail = new PHPMailer();
		$mail->From = 'do_not_reply@richmondsunlight.com';
		$mail->FromName = 'Richmond Sunlight';
		$mail->AddAddress('"'.$user['name'].'" <'.$user['email'].'>');
		//$mail->AddBCC('waldo@jaquith.org');
		$mail->Body = stripslashes($email_body);
		$mail->Subject = $email_subject;
		$mail->IsHTML(false);
		if (!$mail->Send())
		{
			echo 'Failed to send mail to '.$user['email'].'.</p>';
		}
		else
		{
			echo '<p>Mail sent to '.$user['email'].'.</p>';
		}
		
		// log this activity to a database audit table

	}

?>
