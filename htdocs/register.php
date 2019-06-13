<?php

###
# Register
#
# PURPOSE
# Allows the user to establish an account.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

$log = new Log;

# PAGE METADATA
$page_title = 'Register';
$site_section = '';

# INITIALIZE SESSION
session_start();

$html_head = '<script src="/js/vendor/zxcvbn/dist/zxcvbn.js"></script>
			<script src="/js/password-test.js"></script>';

# CUSTOM FUNCTIONS
function display_form($form_data)
{
    $returned_data = '
		<form method="post" action="/account/register/" id="registration">
			<fieldset>
				<legend>Create Your Account</legend>
				<table>
					<tr>
						<th><label for="name">Name</label></th>
						<td>
							<input type="text" id="name" name="form_data[name]" size="30" maxlength="60" value="' . $form_data['name'] . '" required /><br />
							<small>Only your first name and last initial will be shown publicly.</small>
						</td>
					</tr>
					<tr>
						<th><label for="organization">Organization/Company</label></th>
						<td>
							<input type="text" id="organization" name="form_data[organization]" size="30" maxlength="128" value="' . $form_data['organization'] . '" /><br />
							<small>If you do this stuff professionally and want that known publicly.</small>
						</td>
					</tr>
					<tr>
						<th><label for="email">E-Mail</label></th>
						<td>
							<input type="email" id="email" name="form_data[email]" size="30" maxlength="60" value="' . $form_data['email'] . '" required /><br />
							<small>It’s our secret. No spam, ever. We promise.</small>
						</td>
					</tr>
					<tr>
						<th><label for="password">Password</label></th>
						<td>
							<input type="password" id="password" name="form_data[password]" id="password" size="30" maxlength="255" required />
							<meter min="0" max="4" optimum="4" low="1" high="2" value="0" id="password-strength-meter" class="meter"></meter>
							<p id="password-strength-text"></p>
						</td>
					</tr>
					<tr>
						<th><label for="password2">Password Again</label></th>
						<td>
							<input type="password" id="password2" name="form_data[password_2]" size="30" maxlength="255" required /><br />
							<small>Enter it one more time so we can check for typos.</small>
						</td>
					</tr>
					<tr>
						<th><label for="url">Website Address</label></th>
						<td>
							<input type="url" id="url" name="form_data[url]" size="30" maxlength="60" value="' . $form_data['url'] . '" /><br />
							<small>Only, of course, if you have a website.</small>
						</td>
					</tr>
					<tr>
						<th><label for="zip">ZIP</label></th>
						<td>
							<input type="text" id="zip" name="form_data[zip]" size="30" maxlength="5" value="' . $form_data['zip'] . '" pattern="[0-9]{5}" /><br />
							<small>So we can ID your legislators, for site customization.</small>
						</td>
					</tr>
					<tr>
						<th><label for="mailing_list">Mailing List</label></th>
						<td>
							<input type="checkbox" id="mailing_list" name="form_data[mailing_list]" value="y" ' . (($form_data['mailing_list'] == 'y') ? 'checked="checked"' : '') . ' />
							<small>May we e-mail you (very rarely)?</small>
						</td>
					</tr>
					<tr>
						<th></th>
						<td><input type="submit" name="submit" value="Create My Account" class="submit" /></td>
					</tr>
				</table>
			</fieldset>
			<div style="display: none;">
				<p>Please leave this blank.</p>
				<input type="text" name="age" size="3" />
			</div>
			<input type="hidden" name="form_data[time]" value="' . time() . '" />
		</form>
	';

    return $returned_data;
}

$page_body = '';

if (isset($_POST['submit']))
{
    $form_data = array_map('stripslashes', $_POST['form_data']);
    $form_data = array_map('trim', $form_data);

    # If somebody filled out this form in an implausibly short time (two seconds), then it's a
    # spammer.
    if ((time() - $form_data['time']) <= 2)
    {
        die();
    }

    # Spammers tend to overload the ZIP field with an extra character.
    if (mb_strlen($form_data['zip']) == 6)
    {
        die();
    }

    # Spammers also tend to provide a ZIP of "123456," "10001," and "30332."
    if (($form_data['zip'] == '123456') || ($form_data['zip'] == '10001')  || ($form_data['zip'] == '30332'))
    {
        die();
    }

    # If the email address ends with ".ru", this is a spammer.
    if (mb_substr($form_data['email'], -3) == '.ru')
    {
        die();
    }

    # Spammers tend to give URLs that start with "www." and claim to be with one of three tech
    # companies as their organization. Bar anybody registering in this manner.
    if (
        (mb_substr($form_data['url'], 0, 4) == 'www.')
        &&
        in_array($form_data['organization'], array('Apple', 'AT&T', 'microsoft'))
    ) {
        die();
    }

    # Spammers would also fill out the (hidden) age field.
    if (!empty($form_data['age']))
    {
        die();
    }

    if (empty($form_data['name']))
    {
        $errors[] = 'your name';
    }
    if (empty($form_data['password']))
    {
        $errors[] = 'your choice of password';
    }
    elseif ($form_data['password'] != $form_data['password_2'])
    {
        $errors[] = 'the <em>same</em> password twice';
    }
    elseif (mb_strlen($form_data['password']) < 8)
    {
        $errors[] = 'a password that’s at least 8 characters long';
    }
    if (empty($form_data['email']))
    {
        $errors[] = 'your e-mail address';
    }
    elseif (filter_var($form_data['email'], FILTER_VALIDATE_EMAIL) === FALSE)
    {
        $errors[] = 'a valid e-mail address';
    }
    else
    {
        # Make sure that this isn't a duplicate user account.
        $sql = 'SELECT *
				FROM users
				WHERE email = "' . mysqli_real_escape_string($GLOBALS['db'], $form_data['email']) . '"
				AND password IS NOT NULL';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (mysqli_num_rows($result) > 0)
        {
            $errors[] = 'an e-mail address that’s not already in use; better yet,
				<a href="/account/reset-password/">reset your password</a> and use your existing
				account!';
        }
    }

    if (isset($errors))
    {
        $error_text = implode('</li><li>', $errors);
        $page_body = '
			<div id="messages" class="errors">
				<p>Please provide:</p>
				<ul>
					<li>' . $error_text . '</li>
				</ul>
			</div>';

        # Display the registration form again.
        $page_body .= @display_form($form_data);
    }
    else
    {
        $form_data['password_hash'] = md5($form_data['password']);

        # Validate any provided URL, and silently drop it if it's invalid.
        if (!empty($form_data['url']))
        {

            # If there's an at sign in this URL, then it's probably somebody entering an e-mail
            # address, thinking it's a URL.
            if (mb_strstr($form_data['url'], '@') !== false)
            {
                $form_data['url'] = '';
            }
            else
            {

                # Make URLs lowercase.
                $form_data['url'] = mb_strtolower($form_data['url']);

                # If we've got content, but no schema, prepend a schema.
                if (!mb_stristr($form_data['url'], '://'))
                {
                    $form_data['url'] = 'http://' . $form_data['url'];
                }

                # Validate the URL.
                if (filter_var($form_data['url'], FILTER_VALIDATE_URL) === FALSE)
                {
                    $form_data['url'] = '';
                }
            }
        }

        $form_data = array_map(function ($field) {
            return mysqli_real_escape_string($GLOBALS['db'], $field);
        }, $_POST['form_data']);

        # Generate a random eight-digit hash in case this user has to recover his password.
        $chars = 'bcdfghjklmnpqrstvxyz0123456789';
        $hash = mb_substr(str_shuffle($chars), 0, 8);

        # Assemble the URL-style account creation/update data.
        $user_query = 'dashboard=y&type=free&name=' . urlencode($form_data['name']) . '&email=' . $form_data['email'] .
            '&password=' . $form_data['password'] . '&private_hash=' . $hash;
        if (!empty($form_data['organization']))
        {
            $user_query .= '&organization=' . urlencode($form_data['organization']);
        }
        if (!empty($form_data['url']))
        {
            $user_query .= '&url=' . $form_data['url'];
        }
        if (!empty($form_data['zip']))
        {
            $user_query .= '&zip=' . $form_data['zip'];

            # Get this user's coordinates.
            $location = new Location;
            $location->zip = $form_data['zip'];
            $coordinates = $location->get_coordinates();
            $user_query .= '&latitude=' . $coordinates['lat'] . '&longitude=' . $coordinates['lng'];
        }
        if (!empty($form_data['mailing_list']))
        {
            $user_query .= '&mailing_list=' . $form_data['mailing_list'];
        }

        # Create a brand-new account. Though it's tempting to merge this new account with
        # any existing account data, it's really just more trouble than it's worth.
        $result = create_user($user_query);

        if ($result === FALSE)
        {
            $log->put('Somebody tried to create an account, and it failed entirely. They are frustrated now.', 5);
            $page_body = '<p>Your registration has failed mysteriously, in a way that indicates
				that some sort of a bug is at work. Please do us a favor and <a
				href="/contact/">contact us</a> to report that you got this error. We’ll figure
				out what went wrong and get you set up with an account in no time.</p>';
        }
        else
        {

            # Grab the user data.
            $user = get_user();

            # Generate a random five-digit hash to ID this portfolio. It's in base 30,
            # allowing for a namespace of 24,300,000.
            $chars = 'bcdfghjklmnpqrstvxyz0123456789';
            $hash = mb_substr(str_shuffle($chars), 0, 5);
            $sql = 'INSERT INTO dashboard_portfolios
					SET name = "Bills", public="y", user_id = ' . $user['id'] . ',
					hash = "' . $hash . '", date_created = now()';
            mysqli_query($GLOBALS['db'], $sql);

            # Acknowledge the registration.
            $page_body = '
				<h2>Thanks for Registering!</h2>
				<p>Now that you’re set up, you can start using Photosynthesis to track legislation.</p>

				<p style="font-family: Georgia, Palatino, \'Times New Roman\', Times, sans-serif;
					font-size: 2em; text-align: center; margin: 2em 0;">
					<a href="/photosynthesis/">Get Started &gt;&gt;</a>
				</p>

				<p>(Or, if you prefer, you can just <a href="/">go back to the home page</a>.)';

            $log->put('New user registration: ' . $user['name'], 3);
        }
    }
}

# If we're just loading the page.
else
{
    # Display the login form, checking off the "y" for the mailing list by default.
    $form_data['mailing_list'] = 'y';
    $page_body .= @display_form($form_data);
}

$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
