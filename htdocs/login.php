<?php

###
# Login
#
# PURPOSE
# Sets a cookie on the user's system and returns him to the page he came from.
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
$database = new Database();
$database->connect_mysqli();

$log = new Log();

# PAGE METADATA
$page_title = 'Login';
$site_section = '';

# INITIALIZE SESSION
session_start();

if (isset($_POST['submit'])) {
    $form_data = array_map('stripslashes', $_POST['form_data']);
    if (empty($form_data['password'])) {
        $errors[] = 'your password';
    }
    if (empty($form_data['email'])) {
        $errors[] = 'your email address';
    } elseif (!validate_email($form_data['email'])) {
        $errors[] = 'a valid email address';
    }

    if (isset($errors)) {
        $error_text = implode('</li><li>', $errors);
        $page_body = '
			<div id="messages" class="errors">
				<p>Please provide:</p>
				<ul>
					<li>' . $error_text . '</li>
				</ul>
			</div>';
    } else {
        $form_data = array_map(function ($field) {
            return mysqli_real_escape_string($GLOBALS['db'], $field);
        }, $_POST['form_data']);

        $form_data['password_hash'] = md5($form_data['password']);
        $sql = 'SELECT id, name, cookie_hash
				FROM users
				WHERE email = "' . $form_data['email'] . '" AND password = "' . $form_data['password_hash'] . '"';
        $result = mysqli_query($GLOBALS['db'], $sql);

        if (mysqli_num_rows($result) == 0) {
            $page_body = '<div id="messages" class="errors">That email/password combination didn’t work.</div>';
        } else {
            $user = mysqli_fetch_array($result);
            $_SESSION['id'] = $user['cookie_hash'];

            # We store the user's name in session data because a) it's a handy shortcut to refer
            # to the user by name and b) it enables Mint to track users by name.
            if (!empty($user['name'])) {
                $_SESSION['name'] = $user['name'];
            }

            # Gather up the user's Photosynthesis portfolio data and store it in the session data,
            # to be used throughout the site.
            $sql = 'SELECT id, hash, name, watch_list_id
					FROM dashboard_portfolios
					WHERE watch_list_id IS NULL AND user_id=' . $user['id'] . '
					ORDER BY name ASC';
            $result = mysqli_query($GLOBALS['db'], $sql);
            if (mysqli_num_rows($result) > 0) {
                while ($portfolio = mysqli_fetch_array($result)) {
                    $portfolio = array_map('stripslashes', $portfolio);

                    # Store the name and ID of this portfolio in the session, for use on the
                    # rest of the site.
                    $_SESSION['portfolios'][] = $portfolio;
                }

                # Indicate via session data that this is a registered user.
                $_SESSION['registered'] = 'y';
            }

            $log->put('User ' . $user['name'] . ' has logged in.', 2);

            if (empty($form_data['return_uri'])) {
                $form_data['return_uri'] = '/';
            }

            header('Location: https://' . $_SERVER['SERVER_NAME'] . urldecode($form_data['return_uri']));
            exit();
        }
    }
}


if (!isset($_POST['submit'])) {
    $page_body .= '<div style="width: 100%; font-size: 2em; text-align: center; font-family: Georgia, \'Times New Roman\',
		Times, serif; margin: 1em 0;"><p>Don’t have an account yet? <a href="/account/register/">Register now!</a></p></div>';
}

# Display the login form.
$page_body .= login_form();

$page_body .= '<small><a href="/account/reset-password/">Forgot your password?</a></small>';

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
