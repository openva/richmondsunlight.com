<?php

###
# Photosynthesis Home
#
# PURPOSE
# The home page.
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
$page_title = 'Photosynthesis';
$site_section = 'photosynthesis';

# INITIALIZE SESSION
session_start();

# See if the user is logged in.
$user = @get_user();
if (
        (@logged_in() === false)
        ||
        ((@logged_in() === true) && empty($user['type']))
) {
    # If the user isn't logged in, have the user create an account (or log in).
    header('Location: https://' . $_SERVER['SERVER_NAME'] . '/account/login/?return_uri=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

# If the user is logged in, get the user data.
$user = @get_user();

# ADDITIONAL HTML HEADERS
$html_head = '
	<link rel="stylesheet" href="/css/photosynthesis.css" type="text/css" />
    <script src="/js/vendor/jeditable/dist/jquery.jeditable.min.js"></script>
	<script>
		$(document).ready(function() {
			$(".edit").editable("https://' . $_SERVER['SERVER_NAME'] . '/photosynthesis/ajax-bill-notes.php", {
                cssclass: "comments",
                type: "textarea",
				cancel: "cancel",
				submit: "OK",
				indicator: "Saving...",
				tooltip: "Click to edit.",
				submitdata: {user_hash: "' . $user['private_hash'] . '"}
			});
		});
	</script>';

# Start displaying the main page.
$page_body = '';

# Generate a list of this user's portfolios.
$sql = 'SELECT id, hash, name, watch_list_id
		FROM dashboard_portfolios
		WHERE watch_list_id IS NULL AND user_id=' . $user['id'] . '
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);

# If the user has no portfolios. It shouldn't happen, but it could.
if (mysqli_num_rows($result) == 0) {
    # We want a portfolio to exist at all times. Create one.
    $sql = 'INSERT INTO dashboard_portfolios
			SET name = "Bills", public="y", user_id = ' . $user['id'] . ',
			date_created = now()';
    mysqli_query($GLOBALS['db'], $sql);
    $bypass = 1;
}

# If the user has at least one portfolio, or if one was just created.
if ((mysqli_num_rows($result) > 0) || ($bypass == 1)) {
    # Display the header for the bill add form field.
    $page_body = '
		<div id="add-bill">
			<form method="post" action="/photosynthesis/process-actions.php">
				<label for="add-bill">Bill #</label>
				<input type="text" size="7" maxlength="9" name="add-bill" id="add-bill" />';

    # Store the portfolio ID in a hidden form field, if there's just one portfolio.
    if (mysqli_num_rows($result) == 1) {
        $portfolio = mysqli_fetch_array($result);
        $portfolio = array_map('stripslashes', $portfolio);
        $page_body .= '<input type="hidden" name="portfolio" value="' . $portfolio['hash'] . '" />';

        # Store the name and ID of this portfolio in the session, for use on the
        # rest of the site.
        $_SESSION['portfolios'][0] = $portfolio;
    }

    # If there are multiple portfolios, display them as a SELECT.
    elseif (mysqli_num_rows($result) > 1) {
        $page_body .= '
					<select name="portfolio" size="1">
					<option disabled>Select a Portfolio</option>';
        while ($portfolio = mysqli_fetch_array($result)) {
            $portfolio = array_map('stripslashes', $portfolio);
            $page_body .= '
						<option value="' . $portfolio['hash'] . '">' . $portfolio['name'] . '</option>';

            # Store the name and ID of each portfolio in the session, for use on the
            # rest of the site.
            $_SESSION['portfolios'][] = $portfolio;
        }
        $page_body .= '
					</select>';
    }

    # The footer for the bill add form field.
    $page_body .= '
				<input type="submit" name="submit" value="Add">
			</form>
		</div>';
}

# Select the user's list of portfolios.
$sql = 'SELECT id, name, hash, notes, watch_list_id
		FROM dashboard_portfolios
		WHERE user_id=' . $user['id'] . '
		ORDER BY name ASC';
$result = mysqli_query($GLOBALS['db'], $sql);

if (mysqli_num_rows($result) > 0) {
    while ($portfolio = mysqli_fetch_array($result)) {
        $portfolio = array_map('stripslashes', $portfolio);

        if (!empty($portfolio['watch_list_id'])) {
            $portfolio['type'] = 'smart';
        } else {
            $portfolio['type'] = 'normal';
        }

        $page_body .= '
		<div class="portfolio">
			<a name="' . $portfolio['hash'] . '"></a>
			<div class="name">';

        # Only show the portfolio editing options to paid users.
        if ($user['type'] == 'paid') {
            $page_body .= '
				<a href="/photosynthesis/' . $portfolio['hash'] . '/" title="View the public portfolio"><h1>' . $portfolio['name'] . '</a></h1>
				<div class="type">' . (($portfolio['type'] == 'smart') ? 'Smart ' : '') . 'Portfolio</div>
				<div class="rss"><a href="/photosynthesis/rss/portfolio/' . $portfolio['hash'] . '/" title="Subscribe to this portfolio via RSS"><img src="/images/rss-icon.png" alt="RSS" /></a></div>';
        } else {
            $page_body .= '<h1>' . $portfolio['name'] . '</h1>
				<div class="rss"><a href="/photosynthesis/rss/portfolio/' . $portfolio['hash'] . '/" title="Subscribe to this portfolio via RSS"><img src="/images/rss-icon.png" alt="RSS" /></a></div>';
        }

        $page_body .= '</div>';

        # Display the contents of the portfolio.
        $page_body .= show_portfolio($portfolio, $user['id']);

        # Only show portfolio editing and deletion options to paid users.
        if ($user['type'] == 'paid') {
            $page_body .= '
			<div class="modify">
				<a href="/photosynthesis/portfolios/delete/' . $portfolio['hash'] . '/" title="Stop tracking these bills"
					onclick="return confirm(\'Are you sure you want to remove ' . addslashes($portfolio['name']) . '?\')">delete</a>
				<a href="/photosynthesis/portfolios/edit/' . $portfolio['hash'] . '/" title="Modify this portfolio">edit</a> &nbsp;
			</div>';
        }

        $page_body .= '
			<div>' . $portfolio['notes'] . '</div>
		</div>';

        # Preserve the portfolio hash to use below, when presenting the user with the public URL
        # for his portfolio.
        $portfolio_hash = $portfolio['hash'];
    }
}

# Give paid users the option to add a new portfolio.
if ($user['type'] == 'paid') {
    $page_body .= '
	<div id="create-portfolios" class="tabs">
		<h1>Create a Portfolio</h1>
		<ul id="create-portfolios">
			<li><a href="#create-portfolio">Portfolio</a></li>
			<li><a href="#create-smart-portfolio">Smart Portfolio</a></li>
		</ul>

		<div id="create-portfolio">
			' . @portfolio_form() . '
		</div>

		<div id="create-smart-portfolio">
			' . @smart_portfolio_form() . '
		</div>
	</div>';
}


# Inform free users that their portfolio is public.
if ($user['type'] == 'free') {
    $page_body .= '
		<p>Share your Photosynthesis portfolio with others! Anybody can see the bills that
		you’re tracking at <code><a href="https://www.richmondsunlight.com/photosynthesis/'
        . $portfolio_hash . '/">https://www.richmondsunlight.com/photosynthesis/' . $portfolio_hash
        . '/</a></code>.</p>';
}

# The last thing that we do is up the last access date in the session data.
$_SESSION['last_access'] = time();

# OUTPUT THE PAGE
$page = new Page();
$page->page_title = $page_title;
$page->page_body = $page_body;
$page->page_sidebar = $page_sidebar;
$page->site_section = $site_section;
$page->html_head = $html_head;
$page->process();
