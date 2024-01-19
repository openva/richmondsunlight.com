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
session_start();

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
    mysqli_query($GLOBALS['db'], $sql);

    # Return the user back to his dashboard listing.
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}




# Delete a bill from a portfolio.
if (isset($_GET['delete-bill'])) {
    $tmp = mysqli_real_escape_string($GLOBALS['db'], $_GET['delete-bill']);
    $tmp = explode('-', $tmp);
    $portfolio_hash = $tmp[0];
    $record_id = $tmp[1];

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
                WHERE id=' . record_id;
        $result = mysqli_query($GLOBALS['db'], $sql);
        $bill = mysqli_fetch_array($result);
        $mc = new Memcached();
        $mc->addServer("127.0.0.1", 11211);
        $mc->delete('comments-' . $bill['id']);
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


# Create a portfolio.
if (isset($_POST['add-portfolio'])) {
    # Localize $form_data and clean it up.
    if (!array($_POST['form_data'])) {
        return false;
    }
    $form_data = array_map('addslashes', $_POST['form_data']);

    # Don't allow a nameless portfolio.
    if (empty($form_data['name'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    # Iterate through $form_data and prepare it to be inserted.
    $form_data = array_map('trim', $_POST['form_data']);
    $form_data = array_map(function ($field) {
        return mysqli_real_escape_string($GLOBALS['db'], $field);
    }, $_POST['form_data']);

    # Generate a random five-digit hash to ID this portfolio. It's in base 30,
    # allowing for a namespace of 24,300,000.
    $chars = 'bcdfghjklmnpqrstvxyz0123456789';
    $hash = mb_substr(str_shuffle($chars), 0, 5);

    $sql = 'INSERT INTO dashboard_portfolios
            SET name = "' . $_POST['form_data']['name'] . '", hash = "' . $hash . '",
            user_id=' . $user['id'] . ', date_created=now()';
    if (!empty($form_data['notes'])) {
        $sql .= ', notes="' . $form_data['notes'] . '"';
    }
    if (!empty($form_data['public'])) {
        $sql .= ', public="' . $form_data['public'] . '"';
    }
    if (!empty($form_data['notify'])) {
        $sql .= ', notify="' . $form_data['notify'] . '"';
    }
    mysqli_query($GLOBALS['db'], $sql);
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

# Create a smart portfolio.
if (isset($_POST['add-smart-portfolio'])) {
    # Localize $form_data and clean it up.
    if (!array($_POST['form_data'])) {
        return false;
    }
    $form_data = array_map('addslashes', $_POST['form_data']);

    # Don't allow a watch list with no criteria.
    if (
        empty($form_data['tag']) && empty($form_data['patron_id'])
        && empty($form_data['committee_id']) && empty($form_data['keyword']) && empty($form_data['status'])
        && empty($form_data['current_chamber'])
    ) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    # Don't allow a nameless portfolio.
    if (empty($form_data['name'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    # Iterate through $form_data and prepare it to be inserted.
    $form_data = array_map('trim', $_POST['form_data']);
    $form_data = array_map(function ($field) {
        return mysqli_real_escape_string($GLOBALS['db'], $field);
    }, $_POST['form_data']);

    # Create the watch list SQL.
    $sql = 'INSERT INTO dashboard_watch_lists
            SET';

    # For each watch list field, create the SQL insert stanza.
    if (!empty($form_data['tag'])) {
        $sql .= ' tag="' . $form_data['tag'] . '",';
    }
    if (!empty($form_data['patron_id'])) {
        $sql .= ' patron_id="' . $form_data['patron_id'] . '",';
    }
    if (!empty($form_data['committee_id'])) {
        $sql .= ' committee_id="' . $form_data['committee_id'] . '",';
    }
    if (!empty($form_data['keyword'])) {
        $sql .= ' keyword="' . $form_data['keyword'] . '",';
    }
    if (!empty($form_data['status'])) {
        $sql .= ' status="' . $form_data['status'] . '",';
    }
    if (!empty($form_data['current_chamber'])) {
        $sql .= ' current_chamber="' . $form_data['current_chamber'] . '",';
    }

    # Finish it up with the UID and timestamp.
    $sql .= ' user_id=' . $user['id'] . ', date_created=now()';

    # Perform the insert and preserve the watch list ID.
    mysqli_query($GLOBALS['db'], $sql);
    $watch_list_id = mysqli_insert_id($GLOBALS['db']);

    # Generate a random five-digit hash to ID this portfolio. It's in base 30,
    # allowing for a namespace of 24,300,000.
    $chars = 'bcdfghjklmnpqrstvxyz0123456789';
    $hash = mb_substr(str_shuffle($chars), 0, 5);

    $sql = 'INSERT INTO dashboard_portfolios
            SET name = "' . $form_data['name'] . '", watch_list_id = ' . $watch_list_id . ',
            user_id=' . $user['id'] . ', hash="' . $hash . '", date_created=now()';
    if (!empty($form_data['notes'])) {
        $sql .= ', notes="' . $form_data['notes'] . '"';
    }
    if (!empty($form_data['public'])) {
        $sql .= ', public="' . $form_data['public'] . '"';
    }
    if (!empty($form_data['notify'])) {
        $sql .= ', notify="' . $form_data['notify'] . '"';
    }
    $result = mysqli_query($GLOBALS['db'], $sql);
    $portfolio_id = mysqli_insert_id($GLOBALS['db']);

    populate_smart_portfolio($portfolio_id);

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->process();
