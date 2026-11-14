<?php

###
# Process Actions
#
# PURPOSE
# Receives functions and processes them. The intention is to replace this
# with an AJAX equivalent.
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

# LOCALIZE VARIABLES

# SEE IF HE'S LOGGED IN AND DEAL WITH HIM ACCORDINGLY
if (@logged_in() === false) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
$user = @get_user();



# Add a new bill to a portfolio.
// A real problem with this is is that if the bill addition fails, no error message
// is generated. People should be informed why it has failed.
if (isset($_POST['add-bill'])) {
    $bill_number = mysqli_real_escape_string($GLOBALS['db'], $_POST['add-bill']);
    $portfolio_hash = mysqli_real_escape_string($GLOBALS['db'], $_POST['portfolio']);

    # Strip out spaces from bill numbers (i.e. HB 1).
    $bill_number = str_replace(' ', '', $bill_number);

    # Normalize to lowercase to match how bills are stored in the database.
    $bill_number = strtolower($bill_number);

    $sql = 'INSERT INTO dashboard_bills
            SET bill_id =
                (SELECT id
                FROM bills
                WHERE number="' . $bill_number . '" AND session_id = ' . SESSION_ID . '),
            user_id=' . $user['id'] . ', portfolio_id=
                (SELECT id
                FROM dashboard_portfolios
                WHERE hash="' . $portfolio_hash . '"),
            date_created=now()';
    $result = mysqli_query($GLOBALS['db'], $sql);

    # If this is an AJAX request, return JSON instead of redirecting.
    if (@$_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        if ($result && mysqli_affected_rows($GLOBALS['db']) > 0) {
            echo json_encode(['success' => true, 'record_id' => mysqli_insert_id($GLOBALS['db'])]);
        } else {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Bill could not be added.']);
        }
        exit;
    }

    # Return the user back to his dashboard listing.
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}




# Delete a bill from a portfolio.
if (isset($_GET['delete-bill']) || isset($_POST['delete-bill'])) {
    $raw = isset($_POST['delete-bill']) ? $_POST['delete-bill'] : $_GET['delete-bill'];
    $tmp = mysqli_real_escape_string($GLOBALS['db'], $raw);
    $tmp = explode('-', $tmp);
    $portfolio_hash = $tmp[0];
    $record_id = (int) ($tmp[1] ?? 0);

    # Delete the bill record.
    $sql = 'DELETE FROM dashboard_bills
            WHERE id=' . $record_id . ' AND user_id=' . $user['id'] . '
            AND portfolio_id = (SELECT id
                FROM dashboard_portfolios
                WHERE hash="' . $portfolio_hash . '")';
    mysqli_query($GLOBALS['db'], $sql);

    /*
        * Clear the Memcached cache of comments on this bill, since Photosynthesis comments are
        * among them.
        */
    if (MEMCACHED_SERVER != '') {
        $sql = 'SELECT bill_id AS id
                FROM dashboard_bills
                WHERE id=' . (int) $record_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        $bill = mysqli_fetch_array($result);
        $mc = new Memcached();
        $mc->addServer("127.0.0.1", 11211);
        $mc->delete('comments-' . $bill['id']);
    }

    # If this is an AJAX request, return JSON instead of redirecting.
    if (@$_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    # Return the user to the dashboard.
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}




# Delete a portfolio.
if (isset($_GET['delete-portfolio'])) {
    # Localize and make safe the portfolio hash.
    $portfolio_hash = mysqli_real_escape_string($GLOBALS['db'], $_GET['delete-portfolio']);

    # Start off by getting the portfolio's ID and its watch list ID. If there is a watch list
    # ID, we'll use it below to delete the watch list.
    $sql = 'SELECT id, hash, watch_list_id
            FROM dashboard_portfolios
            WHERE hash="' . $portfolio_hash . '"';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) == 0) {
        die('Error: No portfolio found. Could not delete it.');
    }
    $portfolio = mysqli_fetch_array($result);

    # Remove all of the bills associated with this portfolio.
    $sql = 'DELETE FROM dashboard_bills
            WHERE portfolio_id=' . $portfolio['id'] . ' AND user_id=' . $user['id'];
    mysqli_query($GLOBALS['db'], $sql);

    # Delete the portfolio.
    $sql = 'DELETE FROM dashboard_portfolios
            WHERE id=' . $portfolio['id'] . ' AND user_id=' . $user['id'];
    mysqli_query($GLOBALS['db'], $sql);

    # Finally, if this is a smart portfolio, remove the watch list entry.
    if (!empty($portfolio['watch_list_id'])) {
        $sql = 'DELETE FROM dashboard_watch_lists
                WHERE id = ' . $portfolio['watch_list_id'];
        mysqli_query($GLOBALS['db'], $sql);
    }

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}


/*
 * The posted portfolio form's fields, trimmed, or false if there isn't a form.
 */
function posted_portfolio_form()
{
    if (!isset($_POST['form_data']) || !is_array($_POST['form_data'])) {
        return false;
    }
    $form_data = [];
    foreach ($_POST['form_data'] as $field => $value) {
        if (is_string($value)) {
            $form_data[$field] = trim($value);
        }
    }
    return $form_data;
}

/*
 * Insert a portfolio for this user, with the posted form's name, notes, and settings. Returns the
 * new portfolio's ID.
 */
function insert_portfolio($form_data, $user_id, $watch_list_id = null)
{
    # Generate a random five-digit hash to ID this portfolio. It's in base 30,
    # allowing for a namespace of 24,300,000.
    $chars = 'bcdfghjklmnpqrstvxyz0123456789';
    $hash = mb_substr(str_shuffle($chars), 0, 5);

    $sql = 'INSERT INTO dashboard_portfolios
            SET name = ?, hash = ?, user_id = ?, date_created = now()';
    $params = [$form_data['name'], $hash, (int) $user_id];
    if (isset($watch_list_id)) {
        $sql .= ', watch_list_id = ?';
        $params[] = (int) $watch_list_id;
    }
    foreach (['notes', 'public', 'notify'] as $field) {
        if (!empty($form_data[$field])) {
            $sql .= ', ' . $field . ' = ?';
            $params[] = $form_data[$field];
        }
    }
    mysqli_execute_query($GLOBALS['db'], $sql, $params);
    return mysqli_insert_id($GLOBALS['db']);
}

# Create a portfolio.
if (isset($_POST['add-portfolio'])) {
    $form_data = posted_portfolio_form();

    # Don't allow a nameless portfolio.
    if (empty($form_data['name'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    insert_portfolio($form_data, $user['id']);
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

# Create a smart portfolio.
if (isset($_POST['add-smart-portfolio'])) {
    $form_data = posted_portfolio_form();

    # The watch list's criteria. Legislators and committees are named by their IDs.
    $criteria = [];
    foreach (['tag', 'keyword', 'status', 'current_chamber'] as $field) {
        if (!empty($form_data[$field])) {
            $criteria[$field] = $form_data[$field];
        }
    }
    foreach (['patron_id', 'committee_id'] as $field) {
        if (!empty($form_data[$field]) && ctype_digit($form_data[$field])) {
            $criteria[$field] = (int) $form_data[$field];
        }
    }

    # Don't allow a watch list with no criteria, or a nameless portfolio.
    if (empty($criteria) || empty($form_data['name'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    # Create the watch list, and preserve its ID.
    $sql = 'INSERT INTO dashboard_watch_lists
            SET ' . implode(' = ?, ', array_keys($criteria)) . ' = ?, user_id = ?, date_created = now()';
    mysqli_execute_query($GLOBALS['db'], $sql, [...array_values($criteria), (int) $user['id']]);
    $watch_list_id = mysqli_insert_id($GLOBALS['db']);

    $portfolio_id = insert_portfolio($form_data, $user['id'], $watch_list_id);
    populate_smart_portfolio($portfolio_id);

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

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
