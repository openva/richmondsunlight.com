<?php

###
# Delete Tags via Ajax
#
# PURPOSE
# Deletes selected tags.
#
###

/*
 * Set up JSON-based error handling
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Convert any warning/notice/fatal into a clean JSON error and exit
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Respect @-suppression
    if (!(error_reporting() & $errno)) {
        return false;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server error.']);
    return true; // handled
});

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Server error.']);
    }
});

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

header('Content-Type: application/json; charset=utf-8');

# Grab the user data.
$user = get_user();

// Require trusted user.
if ($user == false || $user['trusted'] == 'n') {
    header('HTTP/1.0 403 Forbidden');
    $message = array('error' => 'You do not have permission to delete tags.');
    echo json_encode($message);
    exit();
}

// LOCALIZE VARIABLES
if (isset($_POST['tag_id']) && is_numeric($_POST['tag_id']) && strlen($_POST['tag_id']) < 10) {
    $tag_id = $_POST['tag_id'];
} else {
    header('HTTP/1.0 400 Bad Request');
    $message = array('error' => 'Missing or invalid tag ID.');
    echo json_encode($message);
    exit();
}
if (isset($_POST['bill_id']) && is_numeric($_POST['bill_id']) && strlen($_POST['bill_id']) < 10) {
    $bill_id = $_POST['bill_id'];
} else {
    header('HTTP/1.0 400 Bad Request');
    $message = array('error' => 'Missing or invalid bill ID.');
    echo json_encode($message);
    exit();
}
if (isset($_POST['tag']) && strlen($_POST['tag']) <= 30) {
    $tag = $_POST['tag'];
}

# Assemble the delete SQL
$sql = 'DELETE FROM tags
        WHERE id=' . $tag_id .' AND bill_id=' . $bill_id;
$result = mysqli_query($GLOBALS['db'], $sql);

/*
* If there was a database error.
*/
if (!$result) {
    header('HTTP/1.0 500 Internal Server Error');
    $message = array('error' => 'Tag could not be deleted.');
    echo json_encode($message);
    exit();
}

# Delete the bill from Memcached.
if (MEMCACHED_SERVER != '') {
    $mc = new Memcached();
    $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
    $mc->delete('bill-' . $bill_id);
}

$log = new Log();
$result = $log->put('Tag deleted: ' . $tag . ' from bill ID ' . $bill_id, 2);

/*
 * Send a 200 OK HTTP header, to indicate success.
 */
http_response_code(200);
echo json_encode(['status' => 'deleted']);
exit();
