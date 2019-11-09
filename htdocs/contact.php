<?php

###
# Contact
#
# PURPOSE
# Let people provide feedback.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# LOCALIZE AND CLEAN UP VARIABLES
if (isset($_POST['form_data']))
{
    $form_data = $_POST['form_data'];
}

# PAGE METADATA
$page_title = 'Contact';
$site_section = '';

# PAGE CONTENT

function show_form($form_data)
{
    $returned_data = '
    <style>
        fieldset {
            padding: 0.5em;
        }
        label {
            display: block;
            font-weight:bold;
        }
    </style>
    <form name="comments" method="post" action="/contact/">
    
        <fieldset>
            <label for="message-name">Your name</label>
            <input type="text" name="form_data[name]" id="message-name" size="30" value="' . $form_data['name'] . '" />
        </fieldset>

        <fieldset>
            <label for="message-email">Your e-mail address</label>
            <input type="text" name="form_data[email]"  id="message-email" size="30" value="' . $form_data['email'] . '" />
        </fieldset>

        <fieldset>
            <label for="message-subject">Subject</label>
            <input type="text" name="form_data[subject]" id="message-subject" size="30" value="' . $form_data['subject'] . '" />
        </fieldset>

        <fieldset>
            <label for="message-comments">Message</label>
            <textarea name="form_data[comments]" id="message-comments" cols="50" rows="10">' . $form_data['comments'] . '</textarea>
        </fieldset>

		<div style="display: none;">
			<input type="text" size="2" maxlength="2" name="form_data[zip]" id="message-zip" />
			<label for="message-zip">Leave this field empty</label><br />
		</div>

		<p><input type="submit" name="submit" value="Send Mail"></p>
	</form>';
    return $returned_data;
}

# If the form has been submitted
if (isset($_POST['form_data']))
{

    # Give spammers the boot.
    if (!empty($form_data['zip']))
    {
        die();
    }

    # Filter out newlines to block injection attacks.
    $form_data['email'] = preg_replace("/\r/", "", $form_data['email']);
    $form_data['email'] = preg_replace("/\n/", "", $form_data['email']);
    $form_data['name'] = preg_replace("/\r/", "", $form_data['name']);
    $form_data['name'] = preg_replace("/\n/", "", $form_data['name']);

    # Limit the string length and strip slashes; the former being to, again, block injection attacks.
    $form_data = array_map('stripslashes', $form_data);
    $form_data = array_map('trim', $form_data);
    $form_data['subject'] = mb_substr($form_data['subject'], 0, 80);
    $form_data['name'] = mb_substr($form_data['name'], 0, 80);
    $form_data['email'] = mb_substr($form_data['email'], 0, 50);

    # Make sure it's all good.
    if (empty($form_data['name']))
    {
        $errors[] = 'your name is missing';
    }
    if (empty($form_data['email']))
    {
        $errors[] = 'your email address is missing';
    }
    elseif (!validate_email($form_data['email']))
    {
        $errors[] = 'your email address is not a valid email address';
    }
    if (empty($form_data['subject']))
    {
        $errors[] = 'the subject of your message is missing';
    }
    if (empty($form_data['comments']))
    {
        $errors[] = 'the contents of your message are missing';
    }

    preg_match_all('/https?:/', $form_data['comments'], $matches);
    if (count($matches[0]) >= 3)
    {
        $errors[] = 'there are ' . count($matches[0])  . ' website addresses in your email — ' .
            'that’s a hallmark of spam, so please drop it down to no more than 2';
    }


    if (isset($errors))
    {
        $page_body = '
		<div class="error">
			<p>All is not well with your e-mail — please correct the following:</p>
			<ul>';
        foreach ($errors as $error)
        {
            $page_body .= '<li>' . $error . '</li>';
        }
        $page_body .= '
			</ul>
		</div>';
        $page_body .= show_form($form_data);
    }
    else
    {
        $form_data['comments'] = 'From: "' . $form_data['name'] . '" <' . $form_data['email'] . '>'
            . "\n\n" . $form_data['comments'];

        mail(
            'waldo@jaquith.org',
            $form_data['subject'],
            $form_data['comments'],
        'From: waldo@jaquith.org' . "\n" .
        'Reply-To: ' . $form_data['name'] . ' <' . $form_data['email'] . ">\n" .
        'X-Originating-IP: ' . $_SERVER['REMOTE_ADDR'] . "\n" .
        'X-Originating-URL: ' . $_SERVER['REQUEST_URI']
        );
        $page_body .= '<p>E-mail sent.  Thanks for writing!</p>';
    }
}
else
{

    # Retrieve the user data to populate the comment form.
    # Grab the user data.
    if (logged_in() === TRUE)
    {
        $user = get_user();
        if (!empty($user['name']))
        {
            $form_data['name'] = $user['name'];
        }
        if (!empty($user['email']))
        {
            $form_data['email'] = $user['email'];
        }
    }

    $page_body = '<p>Found a mistake? Have some extra information? Just want to call to say “I love
		you”? Bring it on. <em>Completing this form will send an e-mail to Richmond Sunlight,
		not to any member of the General Assembly</em>.</p>';
    $page_body .= @show_form($form_data);
}


# OUTPUT THE PAGE
$page = new Page;
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->process();
