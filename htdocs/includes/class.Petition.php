<?php

# TODO
# * Modify get_signers() to use MDB2's function to retrieve all results at once.

class Petition
{
	
	// List all petitions.
	function list_all()
	{
		$sql = 'SELECT id, slug, title, date_created
				FROM petitions
				ORDER BY date_created DESC';
		$result = mysql_query($sql);
		
		// If the query fails.
		if ($result === false)
		{
			return false;
		}
		
		$i=0;
		while ($petition = mysql_fetch_array($result))
		{
			$this->list->$i = $petition;
		}
	}
	
	// Define a form
	function form()
	{
		$this->form = '
			<style>
				#petition_title {
					min-width: 400px;
					width: 50%;
				}
				#petition_body {
					min-width: 400px;
					width: 50%;
					height: 20em;
				}
				
				#petition_form label {
					display: block;
					margin-top: 1em;
					clear: left;
				}
				
				#petition_submit {
					display: block;
					clear: left;
				}
			</style>
			<form id="petition_form" method="post" action="/petition.php?new=y">
			
				<label for="petition_title">Title</label>
				<input id="petition_title" type="text" name="form_data[title]" maxlength="256" />
			
				<label for="petition_body">Body</label>
				<textarea id="petition_body" name="form_data[body]"></textarea>';
		
		// If we've specified that this is a new user (that is, if the visitor isn't logged
		// into his account).
		if ($this->new_user === true)
		{
			$this->form .= '
				<label for="user_name">Your Name</label>
				<input id="user_name" type="text" name="form_data[user_name]" />
			
				<label for="user_email">Your E-Mail Address</label>
				<input id="user_email" type="text" name="form_data[user_email]" />';
		}
		
		$this->form .= '
				<input type="submit" id="petition_submit" value="Submit" />
			</form>	
		';
	}

	// Create a new petition. Requires the petition's text and user's ID.
	function create()
	{
		if (!isset($this->title) || !isset($this->text) || !isset($this->user_id))
		{
			return false;
		}
		
		$sql = 'INSERT INTO petitions
				SET slug = "'.$this->generate_slug().'"
				title = "'.mysql_real_escape_string($this->title).'",
				text = "'.mysql_real_escape_string($this->text).'",
				user_id = '.mysql_real_escape_string($this->user_id).'
				date_created=now()';
		$result = mysql_query($sql);
		
		// If the query fails.
		if ($result === false)
		{
			return false;
		}
		
		# Return the ID of the just-added petition.
		$this->petition_id = mysql_insert_id();
	} // end create()
	
	// Generate a random ID for this petition. Strictly speaking, this ought to make sure that the
	// ID isn't in use, but the odds of a namespace collision are 1 in 21,952, so it's just not
	// worth bothering with right now.
	function generate_slug()
	{
		$chars = 'bcdfghjkmnpqrstvwxyz23456789';
		$id = '';
		for ($i=0; $i<3; $i++)
		{
			$id .= substr(str_shuffle($chars), 0, 1);
		}
	}
	
	// Given a petition ID, returns all contents of that petition.
	function get()
	{
		
		// Require a petition ID.
		if (!isset($this->id))
		{
			return false;
		}

		$sql = 'SELECT petitions.date_created AS date, petitions.text, users.name AS creator
				FROM petition
				LEFT JOIN users
					ON petitions.user_id=users.id
				WHERE petitions.id='.mysql_real_escape_string($this->id);
		$result = mysql_query($sql);
		
		// If the query fails.
		if ( ($result === false) || (mysql_num_rows($result) > 0) )
		{
			return false;
		}
		
		$petition = mysql_fetch_object($result);
		
		// Bring this result into the object namespace.
		foreach ($petition as $key => $value)
		{
			$this->$key = $value;
		}
		
		// Include all of the signers' names, too.
		Petition::get_signers();
		
		# Establish the petition URL and save that.
		$petition->url = '/petition/'.$petition->id;
		
		return true;
	}  // end get()
	
	// Retrieve a list of everybody who has signed a given petition.
	function get_signers()
	{
		// Require a petition ID.
		if (!isset($this->id))
		{
			return false;
		}
		
		$sql = 'SELECT users.name, petition_signers.date_created
				FROM petition_signers
				WHERE petition_signers.petition_id='.mysql_real_escape_string($this->petition_id).'
				ORDER BY date_created DESC';
		$result = mysql_query($sql);
		
		// If the query fails.
		if ( ($result === false) || (mysql_num_rows($result) > 0) )
		{
			return false;
		}
		
		// Fetch all results.
		$i=0;
		while ($signer = mysql_fetch_object($result))
		{
			$this->signers->{$i} = $signer;
			$i++;
		}
		
		return true;
	} // end get_signers()
	
	// Add a given user as a signatory to a given petition. Requires a user ID and a petition ID set
	// within the object.
	function sign()
	{
		
		if (!isset($this->user_id) || !isset($this->petition_id))
		{
			return false;
		}
		
		$sql = 'INSERT DELAYED into petition_signers
				SET petition_id='.mysql_real_escape_string($this->petition_id).',
				user_id='.mysql_real_escape_string($this->user_id).',
				ip_address=INET_ATON("'.$_SERVER['REMOTE_ADDR'].'"),
				date_created=now()';
		$result = mysql_query($sql);
		
		// If the query fails.
		if ($result === false)
		{
			return false;
		}
		
		return true;
	} // end sign()

}

?>