<?php

# Allow people to subscribe to (or cease subscribing to) comments to particular bills via e-mail.
class CommentSubscription
{
	
	# Save a new subscription for a given user for a given bill.
	function save()
	{
		if (!isset($this->user_id) || !isset($this->bill_id))
		{
			return false;
		}
		$sql = 'INSERT INTO comments_subscriptions
				SET user_id='.$this->user_id.', bill_id='.$this->bill_id.',
				hash="'.generate_hash(8).'", date_created=now()';
		$result = mysql_query($sql);
		if ($result === false)
		{
			return false;
		}
		
		return true;
	}
	
	# Terminate an existing subscription. Requires the unique hash.
	function delete()
	{
		if (!isset($this->hash))
		{
			return false;
		}
		$sql = 'DELETE FROM comments_subscriptions
				WHERE hash="'.$hash.'"';
		$result = mysql_query($sql);
		if ($result === false)
		{
			return false;
		}
		
		return true;
	}
	
	# Get a listing of all subscriptions for a given bill. We call this "listing" and not "list"
	# because list() is an existing PHP function.
	function listing()
	{
		if (!isset($this->bill_id))
		{
			return false;
		}
		$sql = 'SELECT users.name, users.email, comments_subscriptions.hash
				FROM comments_subscriptions LEFT JOIN users
				ON comments_subscriptions.user_id=users.id
				WHERE comments_subscriptions.bill_id='.$this->bill_id;
		$result = mysql_query($sql);
		if (($result === false) || (mysql_num_rows($result) < 1))
		{
			return false;
		}
		
		# Initialize the array that will store a list of the subscribers for this bill.
		$subscriptions = array();
		
		# Build up that array.
		while ($subscriber = mysql_fetch_array($result))
		{
			$subscriber = array_map('stripslashes', $subscriber);
			$subscriptions[] = $subscriber;
		}
		
		return $subscriptions;
	}
	
	# Determine whether a given user is subscribed to a given bill already. If not, returns false.
	# if so, returns the subscription hash.
	function is_subscribed()
	{
		if (!isset($this->user_id) || !isset($this->bill_id))
		{
			return false;
		}
		$sql = 'SELECT hash
				FROM comments_subscriptions
				WHERE user_id='.$this->user_id.' AND bill_id='.$this->bill_id;
		$result = mysql_query($sql);
		if (mysql_num_rows($result) < 1)
		{
			return false;
		}
		$subscription = mysql_fetch_array($result);
		
		return $subscription['hash'];
	}
	
	# Send out an e-mail notifying a list of subscribers that a new comment has been posted to a
	# bill.
	function send_email()
	{
		# Make sure that we have a list of subscriptions to this bill.
		if ( !isset($this->subscriptions) || !array($this->subscriptions) || (count($this->subscriptions) < 1))
		{
			return false;
		}
		
		# And make sure that we have an array containing the comment, its author, etc.
		if ( !isset($this->comment) || !array($this->comment) || (count($this->comment) < 1))
		{
			return false;
		}
		
		$tmp = new Bill2;
		$tmp->id = $this->bill_id;
		$bill = $tmp->info();
		
		// This is quite likely not the right place to include this, but what the heck?
// THIS INCLUDE IS FAILING. THE FILE CAN'T BE FOUND.
		include 'Mail.php';
		
		# Iterate through every subscriber and e-mail them.
		// I have to suspect that PEAR::Mail can handle this without iterating through, in one
		// fell swoop.
		foreach ($this->subscriptions as $subscriber)
		{
			
			# Put together the headers for our e-mail.
			$headers['Content-Type'] = "text/plain; charset=\"UTF-8\"";
			$headers['Content-Transfer-Encoding'] = "8bit";
			$headers['From'] = '"Richmond Sunlight" <do_not_reply@richmondsunlight.com>';
			$headers['Subject'] = 'Comment on: '.$bill['catch_line'].' ('
				.strtoupper($bill['number']).')';
			//$headers['To'] = '"'.$subscriber['name'].'" <'.$subscriber['email'].'>';
			$headers['To'] = '"Waldo Jaquith" <waldo@jaquith.org>';
			
			# Specify the recipient as being the same as the "To" field.
			$recipient = $headers['To'];
			
			# Assemble the body of the e-mail.
			$body = 'In response to "'.$bill['catch_line'].'" ('.strtoupper($bill['number']).'), '
				.$this->comment['name'].' wrote:'."\r\r".$this->comment['comment']."\r\r"
				.$bill['url']."\r\rUnsubscribe from this Discussion \r"
				.'http://www.richmondsunlight.com/unsubscribe/'.$subscriber['hash'].'/';
			
			# Send the e-mail.
			// THIS SHOULD REALLY BE DONE AS A BASE-64 E-MAIL. 7-bit e-mails are limited to 998
			// characters per line, which is a problem for some of these e-mails. Besides, the
			// content of these e-mails often contain HTML, so that'd be a chance to render those
			// fully, rather than stripping out the HTML.
			/*
			 * $mail_object =& Mail::factory( 'mail' );
			 * $mail_object->send($recipient, $headers, $body);
			 */
			
		} // end foreach
		
		return TRUE;
		
	} // end send_email

}
