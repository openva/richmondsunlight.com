<?php

###
# Dashboard Portfolio Modification
#
# PURPOSE
# Allows the modification of settings for a single portfolio.
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
$page_title = 'Photosynthesis » Edit Portfolio';
$site_section = 'photosynthesis';

# ADDITIONAL HTML HEADERS
$html_head = '<link rel="stylesheet" href="/css/photosynthesis.css" />';

# INITIALIZE SESSION
resume_session();

# Grab the user data. Bail if none is available.
$user = get_user();
if ($user === false) {
    exit();
}

if (!isset($_GET['hash'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    # Clean up and localize the portfolio hash.
    $portfolio['hash'] = mysqli_real_escape_string($GLOBALS['db'], (string) $_GET['hash']);
}

if (isset($_POST['submit'])) {
    // When editing a smart portfolio, make sure to run populate_smart_portfolio() afterwards.

    $form_data = array_map('trim', array_filter((array) ($_POST['form_data'] ?? []), 'is_string'));
    if (empty($form_data['name'])) {
        $errors[] = 'the name of this portfolio';
    }
    if (empty($form_data['public'])) {
        $form_data['public'] = 'n';
    }
    if (empty($form_data['notify'])) {
        $form_data['notify'] = 'none';
    }

    if (isset($errors)) {
        $error_text = implode('</li><li>', $errors);
        $message = '<div id="messages" class="errors">
				<ul>
					<li>' . $error_text . '</li>
				</ul>
			</div>';
    } else {
        $portfolio_id = filter_var(
            $form_data['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $sql = 'UPDATE dashboard_portfolios
				SET name = ?, notify = ?, public = ?, notes = ?
				WHERE id = ? AND user_id = ?';
        $result = $portfolio_id !== false && mysqli_execute_query($GLOBALS['db'], $sql, [
            $form_data['name'],
            $form_data['notify'],
            $form_data['public'],
            (empty($form_data['notes']) ? null : $form_data['notes']),
            $portfolio_id,
            (int) $user['id'],
        ]);

        # If the update to the portfolio didn't work, say so.
        if (!$result) {
            $message = '<div id="messages" class="errors">Sorry: This portfolio could not be edited.</div>';
        }

        # Else if the update worked, proceed.
        else {
            // It would be substantially more efficient to store the existing watch list criteria
            // in a hidden form field, and only update the watch list row and repopulate the list
            // if that watch list has changed.

            # If it's a smart portfolio, we need to update the watchlist. Legislators and
            # committees are named by their IDs.
            if (($form_data['type'] ?? '') == 'smart') {
                $criteria = [];
                foreach (['tag', 'keyword', 'status', 'current_chamber'] as $field) {
                    $criteria[] = empty($form_data[$field]) ? null : $form_data[$field];
                }
                foreach (['patron_id', 'committee_id'] as $field) {
                    $id = (string) ($form_data[$field] ?? '');
                    $criteria[] = ctype_digit($id) ? (int) $id : null;
                }
                $sql = 'UPDATE dashboard_watch_lists
						SET tag = ?, keyword = ?, status = ?, current_chamber = ?, patron_id = ?,
						committee_id = ?
						WHERE
							(SELECT watch_list_id
							FROM dashboard_portfolios
							WHERE id = ?) = dashboard_watch_lists.id
						AND user_id = ?';
                $result = mysqli_execute_query(
                    $GLOBALS['db'],
                    $sql,
                    [...$criteria, $portfolio_id, (int) $user['id']]
                );
                # If the update to the portfolio didn't work, say so.
                if (!$result) {
                    $message = '<div id="messages" class="errors">Sorry: This portfolio could not be edited.</div>';
                } else {
                    # Now we have to repopulate the portfolio
                    populate_smart_portfolio($portfolio_id);
                }
            }

            # If we haven't encountered an error, present a "edit finished" message.
            if (!isset($message)) {
                header('Location: https://www.richmondsunlight.com/photosynthesis/#' . $portfolio['hash']);
                exit;
            }
        }
    }
}

# Assemble the SQL query.
$sql = 'SELECT id, name, notes, notify, public, hash, watch_list_id
		FROM dashboard_portfolios
		WHERE hash="' . $portfolio['hash'] . '" AND user_id=' . $user['id'];
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) == 0) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
$portfolio = mysqli_fetch_array($result);
$portfolio = array_map('stripslashes_nullsafe', $portfolio);

# Display any messages generated by operations. If there are none, simply initialize
# the variable.
if (isset($message)) {
    $page_body = $message;
} else {
    $page_body = '';
}

# Flag this portfolio as a smart portfolio or a normal one.
if (!empty($portfolio['watch_list_id'])) {
    $portfolio['type'] = 'smart';
} else {
    $portfolio['type'] = 'normal';
}

# If it's a smart portfolio, get its watch list data.
if ($portfolio['type'] == 'smart') {
    $sql = 'SELECT id, user_id, tag, patron_id, committee_id, keyword, status,
			current_chamber
			FROM dashboard_watch_lists
			WHERE id = ' . $portfolio['watch_list_id'];
    $result = mysqli_query($GLOBALS['db'], $sql);
    $watch_list = mysqli_fetch_array($result);

    # Clean it up.
    $watch_list = array_map('stripslashes_nullsafe', $watch_list);

    # Merge it into $portfolio.
    $tmp = array_merge($watch_list, $portfolio);
    $portfolio = $tmp;
    unset($watch_list, $tmp);
}

// DISPLAY THE APPROPRIATE FORM
if ($portfolio['type'] == 'smart') {
    $page_body .= @smart_portfolio_form($portfolio);
} else {
    $page_body .= @portfolio_form($portfolio);
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
