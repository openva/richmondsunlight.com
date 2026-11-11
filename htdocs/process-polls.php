<?php

###
# Accept Poll Votes
#
# PURPOSE
# Receives submitted votes and adds them to the database, determining
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

# HOW TO ANSWER
# The bill page's script sends the vote as JSON, and gets the poll's results back, as JSON. A
# form posted without JavaScript gets sent back to the bill page.
$wants_json = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

# LOCALIZE VARIABLES
if ($wants_json) {
    $poll = json_decode(file_get_contents('php://input'), true);
} else {
    $poll = $_REQUEST['poll'] ?? [];
}
if (!is_array($poll)) {
    $poll = [];
}
$bill_id = filter_var($poll['bill_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

# Only send people back to a page on this site. (A path that doesn't start with a slash, like
# "@example.com", would make the browser go to another site.)
$return_to = $poll['return_to'] ?? '';
if (!is_string($return_to) || !str_starts_with($return_to, '/')) {
    $return_to = '/';
}

/*
 * Answer the request, and stop: with the HTTP status and the JSON, for the bill page's script,
 * or by sending a posted form back to the bill page.
 */
function respond($status, $json)
{
    global $wants_json, $return_to;
    if ($wants_json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($json);
    } else {
        header('Location: https://' . $_SERVER['SERVER_NAME'] . $return_to);
    }
    exit;
}

/*
 * The poll's results, for the JSON: the number of votes, and how many of them are yes votes.
 */
function poll_results($bill_id)
{
    $poll = new Poll();
    $poll->bill_id = $bill_id;
    if ($poll->get_results() === false) {
        return null;
    }
    return [
        'total' => (int) $poll->results['total'],
        'yes' => (int) $poll->results['yes'],
    ];
}

# CHECK FOR SPAMMERS
# If the third, DIV-hidden poll option is selected, we know it's a spammer, so just bail.
if (($poll['vote'] ?? '') === 'x') {
    die();
}
if (mb_strlen($_SERVER['HTTP_USER_AGENT'] ?? '') <= 1) {
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
    respond(400, ['error' => 'Choose yes or no.']);
}
if (!logged_in()) {
    create_user();
}
if (empty($_SESSION['id'])) {
    respond(500, ['error' => 'Your vote could not be counted.']);
}

# RECORD THE VOTE
$sql = 'INSERT INTO polls
		SET bill_id = ?, vote = ?, ip = ?,
		user_id =
			(SELECT id
			FROM users
			WHERE cookie_hash = ?),
		date_created = now()';
# (Depending on how mysqli is set to report errors, a failed insert either returns false or
# throws an exception.)
try {
    $result = mysqli_execute_query($GLOBALS['db'], $sql, [
        $bill_id,
        $poll['vote'],
        $_SERVER['REMOTE_ADDR'],
        $_SESSION['id'],
    ]);
} catch (mysqli_sql_exception $e) {
    $result = false;
}
if (!$result) {
    # Somebody (or somebody at this IP address) has voted on this bill already. Show them the
    # results anyway.
    if (mysqli_errno($GLOBALS['db']) === 1062) {
        respond(409, ['error' => 'You’ve already voted on this bill.', 'results' => poll_results($bill_id)]);
    }
    respond(500, ['error' => 'Your vote could not be counted.']);
}

# Clear the cached results, which are now out of date.
if (MEMCACHED_SERVER != '') {
    $mc = new Memcached();
    $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
    $mc->delete('poll-' . $bill_id);
}

respond(200, ['results' => poll_results($bill_id)]);
