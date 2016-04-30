<?php

# Retrieval of comments for a bill.
class Comments
{

	# Get all of this bill's comments, whether posted directly or as Photosynthesis comments.
	function get()
	{
	
		if (empty($this->bill_id))
		{
			return false;
		}
		
		if ( !isset($this->config->get_all) || ($this->config->get_all === TRUE) )
		{
			$this->config->get_comments = TRUE;
			$this->config->get_photosynthesis = TRUE;
		}
		
		# We need to get the summary hash and bill ID to gather comments from identical bills.
		$bill = new Bill2();
		$bill->id = $this->bill_id;
		$bill_info = $bill->info();
		if ($bill_info === FALSE)
		{
			return false;
		}
		
		# Initliaze the array to store comments.
		$comments = array();
		
		# If instructed to retrieve directly posted comments.
		if ($this->config->get_comments === TRUE)
		{
			# Start with directly posted comments.
			$sql = 'SELECT comments.name, comments.date_created, comments.email, comments.url,
					comments.comment, UNIX_TIMESTAMP(comments.date_created) AS timestamp,
					comments.editors_pick, users.representative_id
					FROM comments
					LEFT JOIN users
						ON comments.user_id = users.id
					LEFT JOIN bills
						ON comments.bill_id=bills.id
					WHERE 
					(comments.bill_id=' . mysql_real_escape_string($this->bill_id) . '
					OR
						(bills.summary_hash = "' . $bill_info['summary_hash'] . '"
						AND bills.session_id=' . $bill_info['session_id'] . ')
					)
					AND comments.status="published" 
					ORDER BY comments.date_created ASC';
			$result = @mysql_query($sql);
			if (@mysql_num_rows($result) > 0)
			{
			
				while ($comment = @mysql_fetch_array($result))
				{
				
					# Clean up the data.
					$comment = array_map("stripslashes", $comment);
					
					# Convert newlines to paragraphs.
					$comment['comment'] = nl2p($comment['comment']);
					
					# Add this comment to the comments array.
					$comments[$comment{timestamp}] = $comment;
					
				}
				
			}
		}
		
		# If instructed to retrieve Photosynthesis comments.
		if ($this->config->get_photosynthesis === TRUE)
		{
		
			# Get all of this bill's Photosynthesis notes.
			$sql = 'SELECT users.name, dashboard_bills.date_modified, users.email, users.url,
					dashboard_bills.notes AS comment, dashboard_portfolios.hash,
					dashboard_user_data.organization,
					TIMESTAMPDIFF(SECOND, dashboard_bills.date_modified, CURRENT_TIMESTAMP()) AS seconds_since,
					UNIX_TIMESTAMP(dashboard_bills.date_modified) AS timestamp, users.representative_id
					FROM dashboard_bills
					LEFT JOIN users
						ON dashboard_bills.user_id = users.id
					LEFT JOIN dashboard_portfolios
						ON dashboard_bills.portfolio_id = dashboard_portfolios.id
					LEFT JOIN dashboard_user_data
						ON dashboard_user_data.user_id = users.id
					WHERE dashboard_bills.bill_id='.$bill_info['id'].' AND dashboard_bills.notes IS NOT NULL
					ORDER BY date_modified ASC';
			$result = @mysql_query($sql);
			if (@mysql_num_rows($result) > 0)
			{
			
				while ($comment = @mysql_fetch_array($result))
				{
				
					# Clean up the data.
					$comment = array_map("stripslashes", $comment);
					$comment['comment'] = nl2p($comment['comment']);
					
					# Convert $seconds_since to minutes, hours, days, weeks or months.
					$comment['time_since'] = seconds_to_units($comment['seconds_since']);
					
					# Display the organization, if the portfolio is owned by one. Otherwise, display the
					# user's name.
					if (!empty($comment['organization']))
					{
						$comment['name'] = $comment['organization'];
					}
					else
					{
						# Make the user closer to anonymous.
						$tmp = explode(' ', $comment['name']);
						if (count($tmp) > 1)
						{
							$comment['name'] = $tmp[0].' '.$tmp[1]{0}.'.';
						}
						else
						{
							$comment['name'] = $tmp[0];
						}
					}
					
					# Mark this as being a Photosynthesis.
					$comment['type'] = 'photosynthesis';
					
					# Add this comment to the comments array.
					$comments[$comment{timestamp}] = $comment;
				}
			}
			
		}
		
		# If any comments have been found, return them.
		if (count($comments) > 0)
		{
			return $comments;
		}
		
		return FALSE;
		
	} // end method "get"
	
} // end class "comments"

?>
