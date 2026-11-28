<?php

###
# Process Actions
#
# PURPOSE
# Adds bills to portfolios and removes them, and creates and deletes portfolios, for the
# logged-in user, then sends them back to the page they came from (or, for a script, responds
# with JSON). Each is a POST, with the session's CSRF token (see class.Csrf.php), and changes
# only the user's own portfolios (see class.Portfolio.php).
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once '../includes/settings.inc.php';
include_once '../includes/functions.inc.php';
include_once '../includes/photosynthesis.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# INITIALIZE SESSION
resume_session();

$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

/*
 * Send the user back to the page they came from, if it was on this site, or else to their
 * portfolios. A script gets JSON instead: whether it worked, and anything else to tell it.
 */
function finish($success, $status = 200, $data = [])
{
    if ($GLOBALS['is_ajax']) {
        http_response_code($success ? 200 : $status);
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool) $success] + $data);
        exit;
    }
    if (!$success && $status == 403) {
        http_response_code(403);
        echo 'Sorry: That couldn’t be done. Please go back, reload the page, and try again.';
        exit;
    }
    $back = '/photosynthesis/';
    $referer = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if (
        isset($referer['path']) && str_starts_with($referer['path'], '/')
        && (!isset($referer['host']) || $referer['host'] == ($_SERVER['HTTP_HOST'] ?? ''))
    ) {
        $back = $referer['path'] . (isset($referer['query']) ? '?' . $referer['query'] : '');
    }
    header('Location: ' . $back);
    exit;
}

$user = @get_user();
if (@logged_in() === false || $user === false) {
    finish(false, 401, ['error' => 'You’re not logged in.']);
}
$user_id = (int) $user['id'];

# Everything here changes something, so it has to be posted, from one of this site's pages.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!Csrf::valid()) {
    finish(false, 403, ['error' => 'This page is out of date. Please reload it and try again.']);
}

# Add a bill to a portfolio.
if (isset($_POST['add-bill'])) {
    $record = Portfolio::add_bill((string) ($_POST['portfolio'] ?? ''), $user_id, (string) $_POST['add-bill']);
    if ($record === null) {
        finish(false, 422, ['error' => 'Bill could not be added.']);
    }
    Portfolio::expire_bills([$record['bill_id']]);
    finish(true, 200, ['record_id' => $record['record_id']]);
}

# Remove a bill from a portfolio. It's named by the portfolio's hash and the record's ID, like
# "abcde-123".
if (isset($_POST['delete-bill'])) {
    $parts = explode('-', (string) $_POST['delete-bill'], 2);
    $bill_id = Portfolio::remove_bill($parts[0], $user_id, (int) ($parts[1] ?? 0));
    if ($bill_id === null) {
        finish(false, 404, ['error' => 'That bill isn’t in that portfolio.']);
    }
    Portfolio::expire_bills([$bill_id]);
    finish(true);
}

# Delete a portfolio.
if (isset($_POST['delete-portfolio'])) {
    $bill_ids = Portfolio::delete((string) $_POST['delete-portfolio'], $user_id);
    if ($bill_ids === null) {
        finish(false, 404, ['error' => 'No such portfolio.']);
    }
    Portfolio::expire_bills($bill_ids);
    finish(true);
}

$form_data = (isset($_POST['form_data']) && is_array($_POST['form_data'])) ? $_POST['form_data'] : [];

# Create a portfolio.
if (isset($_POST['add-portfolio'])) {
    $portfolio = Portfolio::create($user_id, $form_data);
    finish($portfolio !== null, 422, ['error' => 'A portfolio needs a name.']);
}

# Create a smart portfolio, and fill it.
if (isset($_POST['add-smart-portfolio'])) {
    $portfolio = Portfolio::create($user_id, $form_data, Portfolio::criteria($form_data));
    if ($portfolio !== null) {
        populate_smart_portfolio($portfolio['id']);
    }
    finish($portfolio !== null, 422, ['error' => 'A smart portfolio needs a name and at least one criterion.']);
}

finish(false, 400, ['error' => 'Nothing to do.']);
