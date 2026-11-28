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

# INITIALIZE SESSION
resume_session();

# The notes are the logged-in user's, on a bill in one of their portfolios, sent with the session's
# CSRF token.
$user = @get_user();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($user === false || !Csrf::valid() || $id === false || !is_string($_POST['value'] ?? null)) {
    http_response_code(403);
    die(' ');
}

# Strip out all tags other than the following. (They're cleaned again whenever they're shown.)
$notes = trim(strip_tags($_POST['value'], '<a><em><strong><i><b><s><blockquote><ol><ul><li>'));

$bill_id = Portfolio::set_notes((int) $user['id'], $id, $notes);
if ($bill_id === null) {
    http_response_code(404);
    die(' ');
}

# Photosynthesis notes are among a bill's comments.
Portfolio::expire_bills([$bill_id]);

# Send the notes back to the browser for display.
echo Portfolio::notes_html($notes);
