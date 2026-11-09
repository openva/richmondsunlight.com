<?php

###
# Accept Poll Votes
#
# PURPOSE
# Receives submitted votesand adds them to the database, determining
# whether they're likely to be spam or require authentication.
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
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# LOCALIZE VARIABLES
$poll = $_REQUEST['poll'] ?? [];
if (!is_array($poll)) {
    die();
}
$bill_id = filter_var($poll['bill_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

# Only send people back to a page on this site. (A path that doesn't start with a slash, like
# "@example.com", would make the browser go to another site.)
$return_to = $poll['return_to'] ?? '';
if (!is_string($return_to) || !str_starts_with($return_to, '/')) {
    $return_to = '/';
}

# CHECK FOR SPAMMERS
# If the third, DIV-hidden poll option is selected, we know it's a spammer, so just bail.
if (($poll['vote'] ?? '') === 'x') {
    die();
}
if (mb_strlen($_SERVER['HTTP_USER_AGENT']) <= 1) {
    die();
}
if (mb_stristr($_SERVER['HTTP_USER_AGENT'], 'curl') === true) {
    die();
}
if (mb_stristr($_SERVER['HTTP_USER_AGENT'], 'Wget') === true) {
    die();
}

# REJECT MISSING OR INVALID POLL VOTES
if (!in_array($poll['vote'] ?? '', ['y', 'n'], true) || $bill_id === false) {
    header('Location: https://' . $_SERVER['SERVER_NAME'] . $return_to);
    exit;
}
if (!logged_in()) {
    create_user();
}

if (!empty($_SESSION['id'])) {
    # ASSEMBLE THE INSERTION SQL
    $sql = 'INSERT INTO polls
			SET bill_id = ?, vote = ?, ip = ?,
			user_id =
				(SELECT id
				FROM users
				WHERE cookie_hash = ?),
			date_created = now()';
    $result = mysqli_execute_query($GLOBALS['db'], $sql, [
        $bill_id,
        $poll['vote'],
        $_SERVER['REMOTE_ADDR'],
        $_SESSION['id'],
    ]);
    if (!$result) {
        die("Poll vote could not be cast.");
    }

    /*
     * Delete the cache.
     */
    if (MEMCACHED_SERVER != '') {
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $mc->delete('poll-' . $bill_id);
    }

    # If the insert was successful, redirect the user back to the page of
    # origin.
    header('Location: https://' . $_SERVER['SERVER_NAME'] . $return_to);
    exit;
}
