<?php

###
# AJAX Handler for Bill Notes
#
# PURPOSE
# Accepts AJAX callbacks for the creation and editing of public notes for individual bills.
#
# NOTES
# None.
#
# TODO
# None.
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
$database = new Database;
$database->connect_old();

# Make sure we have all of the appropriate data.
if (!isset($_POST['user_hash']) || !isset($_POST['id']) || !isset($_POST['value']))
{
    die(' ');
}

# Strip out all tags other than the following.
$notes = trim(strip_tags($_POST['value'], '<a><em><strong><i><b><s><blockquote><embed><ol><ul><li>'));
$hash = mysqli_escape_string($db, $_POST['user_hash']);

# Update the database.
$sql = 'UPDATE dashboard_bills
		SET notes = ' . (empty($notes) ? 'NULL' : '"' . mysqli_escape_string($db, $notes) . '"') . '
		WHERE id=' . mysqli_escape_string($db, $_POST['id']) . '
		AND user_id = (
			SELECT id
			FROM users
			WHERE private_hash="' . mysqli_escape_string($db, $hash) . '"
			LIMIT 1)';
$result = mysqli_query($db, $sql);
if ($result === FALSE)
{
    die(' ');
}

# If the query was successful, send the data back to the browser for display.
else
{

    /*
     * Clear the Memcached cache of comments on this bill, since Photosyntheis comments are
     * among them.
     */
    $sql = 'SELECT bill_id AS id
			FROM dashboard_bills
			WHERE id=' . $_POST['id'];
    $result = mysqli_query($db, $sql);
    $bill = mysqli_fetch_array($result);
    $mc = new Memcached();
    $mc->addServer("127.0.0.1", 11211);
    $mc->delete('comments-' . $bill['id']);

    echo $notes;
}
