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

$page_body = '';

# If we're arriving from another page, remember it so we can send the user back after login.
if (!isset($_POST['submit']) && empty($_GET['return_uri']) && !empty($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    $host = isset($referer['host']) ? $referer['host'] : '';

    // Only allow same-host redirects to avoid open redirects.
    $host_matches = empty($host) || strcasecmp($host, $_SERVER['HTTP_HOST']) === 0 || strcasecmp($host, $_SERVER['SERVER_NAME']) === 0;
    if ($host_matches && !empty($referer['path'])) {
        // Avoid redirect loops back to login-related paths.
        if (!preg_match('#/(account/)?(login|logout|register|reset-password)/?#i', $referer['path'])) {
            $return_uri = $referer['path'];
            if (!empty($referer['query'])) {
                $return_uri .= '?' . $referer['query'];
            }
            $_GET['return_uri'] = $return_uri;
        }
    }
}

if (isset($_POST['submit'])) {
    $form_data = $_POST['form_data'] ?? [];
    if (!is_array($form_data)) {
        $form_data = [];
    }
    $form_data = array_map(fn ($field) => is_string($field) ? $field : '', $form_data);
    $form_data += ['email' => '', 'password' => '', 'return_uri' => ''];
    $form_data['email'] = trim($form_data['email']);

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
        # Passwords have been hashed in three ways: as typed (resetting a password, and registering
        # from now on); after being escaped for SQL (changing it in account settings or
        # Photosynthesis, and logging in until now); and after being escaped and then URL-decoded
        # (registering, until now). Accept any of them, so that nobody gets locked out.
        $password = $form_data['password'];
        $escaped = mysqli_real_escape_string($GLOBALS['db'], $password);
        $sql = 'SELECT id, name, cookie_hash
				FROM users
				WHERE email = ? AND password IN (?, ?, ?)';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [
            $form_data['email'],
            md5($password),
            md5($escaped),
            md5(urldecode($escaped)),
        ]);

        if (mysqli_num_rows($result) == 0) {
            $page_body = '<div id="messages" class="errors">That email/password combination didn’t work.</div>';
        } else {
            $user = mysqli_fetch_array($result);
            $_SESSION['id'] = $user['cookie_hash'];

            # We store the user's name in session data because it's a handy shortcut to refer
            # to the user by name
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
                    $portfolio = array_map('stripslashes_nullsafe', $portfolio);

                    # Store the name and ID of this portfolio in the session, for use on the
                    # rest of the site.
                    $_SESSION['portfolios'][] = $portfolio;
                }

                # Indicate via session data that this is a registered user.
                $_SESSION['registered'] = 'y';
            }

            $log->put('User ' . $user['name'] . ' has logged in.', 2);

            # Only send people back to a page on this site. (A path that doesn't start with a
            # slash, like "@example.com", would make the browser go to another site.)
            $return_uri = urldecode($form_data['return_uri']);
            if (!str_starts_with($return_uri, '/')) {
                $return_uri = '/';
            }

            header('Location: ' . SITE_BASE_URL . $return_uri);
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

// OUTPUT THE PAGE
$page = new Page();
foreach ([
    'page_title',
    'page_body',
    'page_sidebar',
    'site_section',
    'browser_title',
    'html_head',
    'body_tag',
] as $prop) {
    if (isset(${$prop})) {
        $page->{$prop} = ${$prop};
    }
}
$page->process();
