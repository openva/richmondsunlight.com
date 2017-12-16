<?php

	###
	# Photosynthesis Preferences
	# 
	# PURPOSE
	# Individual user settings.
	#
	# NOTES
	# None.
	#
	# TODO
	# None.
	#
	###
	
	# INCLUDES
	# Include any files or libraries that are necessary for this specific
	# page to function.
	include_once('../includes/functions.inc.php');
	include_once('../includes/settings.inc.php');
	include_once('../includes/photosynthesis.inc.php');
	
	# DECLARATIVE FUNCTIONS
	# Run those functions that are necessary prior to loading this specific
	# page.
	$database = new Database;
	$database->connect_old();
	
	# PAGE METADATA
	$page_title = 'Photosynthesis &raquo; Preferences';
	$site_section = 'photosynthesis';
	
	# ADDITIONAL HTML HEADERS
	$html_head = '<link rel="stylesheet" href="/css/photosynthesis.css" type="text/css" />';
	
	# INITIALIZE SESSION
	session_start();
	
	# DEFINE FUNCTIONS
	function show_form($form_data)
	{
		global $user;
		$content = '
			<form method="post" action="'.$_SERVER['REQUEST_URI'].'">
				
				<table class="form">
					<tr><td><label for="name">Name</label></td></tr>
					<tr><td><input type="text" size="20" maxlength="60" id="name" name="form_data[name]" value="'.(!empty($form_data['name']) ? $form_data['name'] : '').'" /></td></tr>
					<tr><td><label for="name">E-Mail Address</label></td></tr>
					<tr><td><input type="text" size="20" maxlength="60" id="email" name="form_data[email]" value="'.(!empty($form_data['email']) ? $form_data['email'] : '').'" /></td></tr>
					<tr><td><label for="name">Password</label></td></tr>
					<tr><td><input type="password" size="20" maxlength="30" id="password" name="form_data[password]" value="'.(!empty($form_data['password']) ? $form_data['password'] : '').'" /></td></tr>
					<tr><td><small>Enter a password to switch to a new one.</small></td>';
		
		if ($user['type'] == 'paid') $content .= '
					<tr><td>
						<fieldset id="email-active">
							<legend>Receive E-Mail Updates?</legend>
							<input type="radio" name="form_data[email_active]" id="email-active-y" value="y"'.(($form_data['email_active'] == 'y') ? ' checked="checked"' : '').' /><label for="email-active-y">Send e-mail updates as scheduled</label>
							<input type="radio" name="form_data[email_active]" id="email-active-n" value="n"'.(($form_data['email_active'] == 'n') ? ' checked="checked"' : '').' /><label for="email-active-n">Temporarily suspend e-mail updates for portfolios</label>
						</fieldset>
					</td></tr>';
		
		$content .= '
					<tr><td><input type="submit" name="submit" value="Save Changes" /></td></tr>
				</table>
				
			</form>';
		
		return $content;
	}
	
	# Grab the user data. Bail if none is available.
	$user = get_user();

	if (isset($_POST['submit']))
	{
	
		$form_data = array_map('stripslashes', $_POST['form_data']);
		
		# Error correction.
		if (empty($form_data['name'])) $errors[] = 'your name';
		if (empty($form_data['email'])) $errors[] = 'your e-mail address';
		if ($user['type'] == 'paid')
		{
			if (empty($form_data['email_active'])) $form_data['email_active'] = 'n';
		}
		
		# Alert the user if any of these errors are show-stoppers.
		if (isset($errors))
		{
			$error_text = implode('</li><li>', $errors);
			$message = '<div id="messages" class="errors">
					<ul>
						<li>'.$error_text.'</li>
					</ul>
				</div>';
		}
		else
		{
			# Clean up the data.
			$form_data = array_map('mysql_real_escape_string', $_POST['form_data']);
			
			# Create a password hash from the password and store that.
			if (!empty($form_data['password'])) $form_data['password_hash'] = md5($form_data['password']);
			
			# Conduct the actual query.
			$sql = 'UPDATE users, dashboard_user_data
					SET users.name = "'.$form_data['name'].'", users.email = "'.$form_data['email'].'"
					'.(!empty($form_data['password_hash']) ? ', users.password = "'.$form_data['password_hash'].'"' : '').'
					'.(!empty($form_data['email_active']) ? ', dashboard_user_data.email_active = "'.$form_data['email_active'].'"' : '').'
					WHERE users.cookie_hash="'.$_SESSION['id'].'"';
			$result = mysql_query($sql);
			
			# Report on the results.
			if (!$result) $message = '<div id="messages" class="errors">Your preferences could not be saved.</div>';
			else $message = '<div id="messages" class="updated">Preferences updated successfully.</div>';
		}
		
	}
	
	# Display the result of the query, if there was one.
	if (isset($message)) $page_body = $message;
	
	# Assemble the SQL query.
	$sql = 'SELECT users.id, users.name, users.email, dashboard_user_data.email_active
			FROM users LEFT JOIN dashboard_user_data ON users.id = dashboard_user_data.user_id
			WHERE users.cookie_hash="'.$_SESSION['id'].'"';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0) login_redirect();
	$preferences = mysql_fetch_array($result);
	$preferences = array_map('stripslashes', $preferences);
	
	# Display the preferences form.
	$page_body .= @show_form($preferences);
	
	# OUTPUT THE PAGE
	/*display_page('page_title='.urlencode($page_title).'&page_body='.urlencode($page_body).'&page_sidebar='.urlencode($page_sidebar).
		'&site_section='.urlencode($site_section).'&html_head='.urlencode($html_head));*/

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();

?>