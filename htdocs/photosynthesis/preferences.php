<?php

###
# Photosynthesis Preferences
#
# PURPOSE
# Individual user settings.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once '../includes/functions.inc.php';
include_once '../includes/settings.inc.php';
include_once '../includes/photosynthesis.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Photosynthesis » Preferences';
$site_section = 'photosynthesis';

# ADDITIONAL HTML HEADERS
$html_head = '<link rel="stylesheet" href="/css/photosynthesis.css" />';

# INITIALIZE SESSION
resume_session();

# DEFINE FUNCTIONS
function show_form($form_data)
{
    $active = ($form_data['email_active'] ?? 'y') ?: 'y';
    return '
        <form method="post" action="/photosynthesis/preferences/">
            ' . Csrf::field() . '
            <table class="form">
                <tr><td><label for="name">Name</label></td></tr>
                <tr><td><input type="text" size="20" maxlength="60" id="name" name="form_data[name]" value="' . Portfolio::text($form_data['name'] ?? '') . '" /></td></tr>
                <tr><td><label for="email">E-Mail Address</label></td></tr>
                <tr><td><input type="email" size="20" maxlength="60" id="email" name="form_data[email]" value="' . Portfolio::text($form_data['email'] ?? '') . '" /></td></tr>
                <tr><td><label for="password">Password</label></td></tr>
                <tr><td><input type="password" size="20" maxlength="30" id="password" name="form_data[password]" value="" autocomplete="new-password" /></td></tr>
                <tr><td><small>Enter a password to switch to a new one.</small></td></tr>
                <tr><td>
                    <fieldset id="email-active">
                        <legend>Receive E-Mail Updates?</legend>
                        <input type="radio" name="form_data[email_active]" id="email-active-y" value="y"' . ($active == 'y' ? ' checked="checked"' : '') . ' /><label for="email-active-y">Send e-mail updates about my portfolios, as each one is set to</label>
                        <input type="radio" name="form_data[email_active]" id="email-active-n" value="n"' . ($active == 'n' ? ' checked="checked"' : '') . ' /><label for="email-active-n">Don’t send me any e-mail updates</label>
                    </fieldset>
                </td></tr>
                <tr><td><input type="submit" name="submit" value="Save Changes" /></td></tr>
            </table>
        </form>';
}

# Grab the user data. Send them to log in if they aren't.
$user = get_user();
if (!Portfolio::can_use($user)) {
    header('Location: /account/login/?return_uri=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
$user_id = (int) $user['id'];

if (isset($_POST['submit'])) {
    $form_data = (isset($_POST['form_data']) && is_array($_POST['form_data'])) ? $_POST['form_data'] : [];
    $form_data = array_map(fn($field) => is_string($field) ? trim($field) : '', $form_data);

    $errors = [];
    if (($form_data['name'] ?? '') === '') {
        $errors[] = 'your name';
    }
    if (!filter_var($form_data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'your e-mail address';
    }

    if (!Csrf::valid()) {
        $message = '<div id="messages" class="errors">Sorry: This page was out of date. Please make your changes again.</div>';
    } elseif (count($errors) > 0) {
        $message = '<div id="messages" class="errors">
                <ul>
                    <li>' . implode('</li><li>', array_map([Portfolio::class, 'text'], $errors)) . '</li>
                </ul>
            </div>';
    } else {
        $sql = 'UPDATE users SET name = ?, email = ?';
        $params = [mb_substr($form_data['name'], 0, 60), mb_substr($form_data['email'], 0, 60)];
        if (($_POST['form_data']['password'] ?? '') !== '' && is_string($_POST['form_data']['password'])) {
            $sql .= ', password = ?';
            $params[] = Password::hash($_POST['form_data']['password']);
        }
        $sql .= ' WHERE id = ?';
        $params[] = $user_id;
        $result = mysqli_execute_query($GLOBALS['db'], $sql, $params);

        # Email settings are in the user's Photosynthesis settings, made if they don't have them.
        Portfolio::ensure_user_data($user_id);
        $result = $result && mysqli_execute_query(
            $GLOBALS['db'],
            'UPDATE dashboard_user_data SET email_active = ? WHERE user_id = ?',
            [($form_data['email_active'] ?? 'y') === 'n' ? 'n' : 'y', $user_id]
        );

        # get_user() keeps a copy of the user's record for an hour.
        if (MEMCACHED_SERVER != '' && class_exists('Memcached')) {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            $mc->delete('user-' . $_SESSION['id']);
        }

        $message = $result
            ? '<div id="messages" class="updated">Preferences updated successfully.</div>'
            : '<div id="messages" class="errors">Your preferences could not be saved.</div>';
    }
}

# The user's preferences, as they are now.
$sql = 'SELECT users.id, users.name, users.email, dashboard_user_data.email_active
        FROM users LEFT JOIN dashboard_user_data ON users.id = dashboard_user_data.user_id
        WHERE users.id = ?';
$result = mysqli_execute_query($GLOBALS['db'], $sql, [$user_id]);
$preferences = $result ? mysqli_fetch_assoc($result) : null;
if (!$preferences) {
    login_redirect();
}
$preferences = array_map('stripslashes_nullsafe', $preferences);

# Display the result of saving them, if they were, and the form.
$page_body = ($message ?? '') . show_form($preferences);

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
