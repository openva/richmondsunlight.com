<?php

###
# Account Profile
#
# PURPOSE
# Edit account information.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/functions.inc.php';
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# PAGE METADATA
$page_title = 'Account';
$site_section = '';

# INITIALIZE SESSION
session_start();

# Include the tabbing code.
$html_head = '<script src="/js/scriptaculous/control-tabs.js"></script>';

# Include the password-strength-meter code.
$html_head = '<script src="/js/vendor/zxcvbn/dist/zxcvbn.js"></script>
			<script src="/js/password-test.js"></script>';

# See if the user is logged in.
if (@logged_in() === false)
{
    # If the user isn't logged in, have the user create an account (or log in).
    header('Location: https://www.richmondsunlight.com/account/login/');
    exit;
}

# If the user is logged in, get the user data.
else
{
    $user = @get_user();
}

# CUSTOM FUNCTIONS
function display_form($form_data)
{
    $returned_data = '
		<form method="post" action="/account/" id="registration">
			<fieldset>
				<legend>Modify Your Account</legend>
				<table>
					<tr>
						<th>Name</th>
						<td>
							<input type="text" name="form_data[name]" size="30" maxlength="60" value="'.$form_data['name'].'" />
						</td>
					</tr>
					<tr>
						<th>Group/Company</th>
						<td>
							<input type="text" name="form_data[organization]" size="30" maxlength="128" value="'.$form_data['organization'].'" /><br />
							<small>If you do this stuff professionally.</small>
						</td>
					</tr>
					<tr>
						<th>E-Mail</th>
						<td>
							<input type="text" name="form_data[email]" size="30" maxlength="60" value="'.$form_data['email'].'" /><br />
							<small>It’s our secret. No spam, ever.</small>
						</td>
					</tr>
					<tr>
						<th>Password</th>
						<td>
							<input type="password" name="form_data[password]" id="password" size="30" maxlength="60" />
							<meter max="4" id="password-strength-meter"></meter>
							<p id="password-strength-text"></p>
						</td>
					</tr>
					<tr>
						<th>Password Again</th>
						<td>
							<input type="password" name="form_data[password_2]" size="30" maxlength="60" /><br />
							<small>Enter it twice to set a new one.</small>
						</td>
					</tr>
					<tr>
						<th>Website Address</th>
						<td>
							<input type="text" name="form_data[url]" size="30" maxlength="60" value="'.$form_data['url'].'" /><br />
							<small>Only, of course, if you have one.</small>
						</td>
					</tr>
					<tr>
						<th>ZIP</th>
						<td>
							<input type="text" name="form_data[zip]" size="30" maxlength="5" value="'.$form_data['zip'].'" /><br />
							<small>For site customization.</small>
						</td>
					</tr>
					<tr>
						<th>Mailing List</th>
						<td>
							<input type="checkbox" name="form_data[mailing_list]" value="y" '.(($form_data['mailing_list'] == 'y') ? 'checked="checked"' : '').' />
							<small>May we e-mail you occasionally?</small>
						</td>
					</tr>
					<tr>
						<th></th>
						<td><input type="submit" name="submit" value="Update My Account" class="submit" /></td>
					</tr>
				</table>
			</fieldset>
		</form>
	';

    return $returned_data;
}

if (isset($_POST['submit']))
{
    $form_data = array_map('stripslashes', $_POST['form_data']);
    $form_data = array_map('trim', $_POST['form_data']);

    # Alert users to mistakes.
    if (empty($form_data['email'])) $errors[] = 'your e-mail address';
    elseif (!validate_email($form_data['email'])) $errors[] = 'a valid e-mail address';
    if (!empty($form_data['password']) || !empty($form_data['password_2']))
    {
        if ($form_data['password'] != $form_data['password_2']) $errors[] = 'the <em>same</em> password twice';
        elseif (strlen($form_data['password']) < 7) $errors[] = 'a password that\'s at least seven characters long';
    }

    # If we find any mistakes, stop the account update process and alert the user.
    if (isset($errors))
    {
        $error_text = implode('</li><li>', $errors);
        $page_body = '
			<div id="messages" class="errors">
				<p>Please provide:</p>
				<ul>
					<li>'.$error_text.'</li>
				</ul>
			</div>';
    }
    else
    {

        # Clean up the data to be inserted into the database.
        $form_data = array_map('mysql_real_escape_string', $_POST['form_data']);

        # A blank mailing list variable is a "no."
        if (empty($form_data['mailing_list'])) $form_data['mailing_list'] = 'n';

        # MD5 the password.
        if (!empty($form_data['password'])) $form_data['password'] = md5($form_data['password']);

        # Assembly the SQL string.
        $sql = 'UPDATE users
				SET name="'.$form_data['name'].'", email="'.$form_data['email'].'"';
        $sql .= ', url=';
        if (empty($form_data['url'])) $sql .= 'NULL';
        else $sql .= '"'.$form_data['url'].'"';
        $sql .= ', zip=';
        if (empty($form_data['zip'])) $sql .= 'NULL';
        else $sql .= '"'.$form_data['zip'].'"';
        $sql .= ', mailing_list=';
        if (empty($form_data['mailing_list'])) $sql .= 'NULL';
        else $sql .= '"'.$form_data['mailing_list'].'"';
        if (!empty($form_data['password'])) $sql .= ', password="'.$form_data['password'].'"';
        $sql .= ' WHERE id='.$user['id'];
        $result = mysql_query($sql);
        if ($result === FALSE) die('Your account could not be updated.');

        # Update the organization data.
        $sql = 'UPDATE dashboard_user_data
				SET organization='.(empty($form_data['organization']) ? 'NULL' : '"'.$form_data['organization'].'"').'
				WHERE user_id='.$user['id'];
        $result = mysql_query($sql);

        header('Location: http://www.richmondsunlight.com/account/?updated');
        exit();
    }

}


if (!isset($_POST['submit']))
{

    $page_body .= '
	<div class="tabs">
	<ul>
		<li><a href="#settings">Settings</a></li>
		<li><a href="#stats">Statistics</a></li>
		<li><a href="#comments">Comments</a></li>
	</ul>

	<div id="settings">';

    if (isset($_GET['updated']))
    {
        $page_body .= '
			<div id="messages" class="updated">Your account has been updated.</div>';
    }
    elseif (isset($_GET['reset']))
    {
        $page_body .= '
			<div id="messages" class="updated">
				You are logged into your account. It would be smart to set a new password now.
			</div>';
    }
    else
    {
        $page_body .= '
			<p>You may change any of your account settings here.</p>';
    }


    # Get all of the user's data.
    $sql = 'SELECT users.id, users.name, users.email, users.url, users.zip, users.mailing_list,
			dashboard_user_data.organization
			FROM users LEFT JOIN dashboard_user_data
			ON users.id=dashboard_user_data.user_id
			WHERE id='.$user['id'];
    $result = mysql_query($sql);
    if (mysql_num_rows($result) == 0)
    {
        die('No user data found.');
    }
    $user_data = mysql_fetch_array($result);

    $user_data = array_map('stripslashes', $user_data);

    # Display the account editing form.
    $page_body .= @display_form($user_data);

    $page_body .= '
		</div>

		<div id="stats">';

    $user_data = new User();
    $stats = $user_data->tagging_stats();
    if ($stats !== false)
    {
        if ($stats->tags == 0)
        {
            $page_body .= '
			<h2>Bill Tagging Stats</h2>
			<p>You haven’t tagged <em>any</em> bills! When looking at bills on the site,
			add a few keywords in the sidebar and help others find those bills. We need your
			help!</p>';
        }
        $page_body .= '
			<h2>Bills Tagged</h2>
			<p>You have provided '.number_format($stats->tags).' tags for '
            .number_format($stats->bills).' bills. Thank you!</p>';
    }

    $page_body .= '
		</div>
		<div id="comments">';
    $comments = $user_data->list_comments();
    if ($comments === false)
    {
        $page_body .= '<p>You have not posted any comments to the site.</p>';
    }
    else
    {
        $page_body .= '<p>The following are the ten comments that you have made most recently.</p>';
        foreach ($comments as $comment)
        {
            $page_body .= '<h3><a href="/bill/'.$comment['bill_year'].'/'.$comment['bill_number'].'/">'
                .strtoupper($comment['bill_number']).'</a>: '.$comment['catch_line']
                .' ('.$comment['date'].')</h3>
				'.nl2p($comment['comment']);
        }
    }
    $page_body .= '
		</div>
	</div>';

}

# OUTPUT THE PAGE

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
