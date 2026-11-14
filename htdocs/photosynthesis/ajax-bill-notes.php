<?php

###
# AJAX Handler for Bill Notes
#
# PURPOSE
# Accepts AJAX callbacks for the creation and editing of public notes for individual bills.
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
$db = $database->connect_mysqli();

# Make sure we have all of the appropriate data.
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || !is_string($_POST['user_hash'] ?? null) || !is_string($_POST['value'] ?? null)) {
    die(' ');
}

# Strip out all tags other than the following.
$notes = trim(strip_tags($_POST['value'], '<a><em><strong><i><b><s><blockquote><ol><ul><li>'));

# Update the database.
$sql = 'UPDATE dashboard_bills
		SET notes = ?
		WHERE id = ?
		AND user_id = (
			SELECT id
			FROM users
			WHERE private_hash = ?
			LIMIT 1)';
$result = mysqli_execute_query($GLOBALS['db'], $sql, [($notes === '' ? null : $notes), $id, $_POST['user_hash']]);
if ($result === false) {
    die(' ');
}

# If the query was successful, send the data back to the browser for display.
else {
    /*
     * Clear the Memcached cache of comments on this bill, since Photosynthesis comments are
     * among them.
     */
    if (MEMCACHED_SERVER != '') {
        $sql = 'SELECT bill_id AS id
                FROM dashboard_bills
                WHERE id = ?';
        $result = mysqli_execute_query($GLOBALS['db'], $sql, [$id]);
        $bill = mysqli_fetch_array($result);
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, 11211);
        $mc->delete('comments-' . $bill['id']);
    }

    echo $notes;
}
