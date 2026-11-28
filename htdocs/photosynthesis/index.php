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
resume_session();

# See if the user is logged in.
$user = @get_user();
if (
        (@logged_in() === false)
        ||
        ((@logged_in() === true) && empty($user['type']))
) {
    # If the user isn't logged in, have the user create an account (or log in).
    header('Location: ' . SITE_BASE_URL . '/account/login/?return_uri=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

# ADDITIONAL HTML HEADERS
# Notes on bills are edited in place, and saved by ajax-bill-notes.php.
$html_head = '
	<link rel="stylesheet" href="/css/photosynthesis.css" type="text/css" />
    <script src="/js/vendor/jeditable/dist/jquery.jeditable.min.js"></script>
	<script>
		$(document).ready(function() {
			$(".edit").editable("/photosynthesis/ajax-bill-notes.php", {
                cssclass: "comments",
                type: "textarea",
				cancel: "cancel",
				submit: "OK",
				indicator: "Saving...",
				tooltip: "Click to edit.",
				submitdata: {csrf_token: ' . json_encode(Csrf::token()) . '}
			});
		});
	</script>';

# Start displaying the main page.
$page_body = '';

# Bills can be added to the user's normal portfolios. Everybody should have one, so if they don't,
# make one.
$normal = Portfolio::for_user((int) $user['id'], true);
if (count($normal) == 0) {
    $created = Portfolio::create((int) $user['id'], ['name' => 'Bills', 'public' => 'y']);
    if ($created !== null) {
        $normal = [$created];
    }
}

if (count($normal) > 0) {
    # Display the header for the bill add form field.
    $page_body = '
		<div id="add-bill-form">
			<form method="post" action="/photosynthesis/process-actions.php">
				' . Csrf::field() . '
				<label for="add-bill">Bill #</label>
				<input type="text" size="7" maxlength="9" name="add-bill" id="add-bill" />';

    # Store the portfolio ID in a hidden form field, if there's just one portfolio.
    if (count($normal) == 1) {
        $page_body .= '<input type="hidden" name="portfolio" value="' . Portfolio::text($normal[0]['hash']) . '" />';
    }

    # If there are multiple portfolios, display them as a SELECT.
    else {
        $page_body .= '
					<select name="portfolio" size="1">
					<option disabled>Select a Portfolio</option>';
        foreach ($normal as $portfolio) {
            $page_body .= '
						<option value="' . Portfolio::text($portfolio['hash']) . '">'
                . Portfolio::text(stripslashes_nullsafe($portfolio['name'])) . '</option>';
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

# All of the user's portfolios.
foreach (Portfolio::for_user((int) $user['id']) as $portfolio) {
    $portfolio = array_map('stripslashes_nullsafe', $portfolio);
    $hash = Portfolio::text($portfolio['hash']);
    $name = Portfolio::text($portfolio['name']);

    $page_body .= '
		<div class="portfolio">
			<a name="' . $hash . '"></a>
			<div class="name">';

    # Only show the portfolio editing options to paid users.
    if ($user['type'] == 'paid') {
        $page_body .= '
				<a href="/photosynthesis/' . $hash . '/" title="View the public portfolio"><h1>' . $name . '</h1></a>
				<div class="type">' . (($portfolio['type'] == 'smart') ? 'Smart ' : '') . 'Portfolio</div>
				<div class="rss"><a href="/photosynthesis/rss/portfolio/' . $hash . '/" title="Subscribe to this portfolio via RSS"><img src="/images/rss-icon.png" alt="RSS" /></a></div>';
    } else {
        $page_body .= '<h1>' . $name . '</h1>
				<div class="rss"><a href="/photosynthesis/rss/portfolio/' . $hash . '/" title="Subscribe to this portfolio via RSS"><img src="/images/rss-icon.png" alt="RSS" /></a></div>';
    }

    $page_body .= '</div>';

    # Display the contents of the portfolio.
    $page_body .= show_portfolio($portfolio, $user['id']);

    # Only show portfolio editing and deletion options to paid users.
    if ($user['type'] == 'paid') {
        $page_body .= '
			<div class="modify">
				<form method="post" action="/photosynthesis/process-actions.php" class="delete-portfolio"
					onsubmit="return confirm(' . Portfolio::text(json_encode('Are you sure you want to remove ' . $portfolio['name'] . '?')) . ')">
					' . Csrf::field() . '
					<input type="hidden" name="delete-portfolio" value="' . $hash . '" />
					<button type="submit" title="Stop tracking these bills">delete</button>
				</form>
				<a href="/photosynthesis/portfolios/edit/' . $hash . '/" title="Modify this portfolio">edit</a> &nbsp;
			</div>';
    }

    $page_body .= '
			<div>' . Portfolio::notes_html($portfolio['notes']) . '</div>
		</div>';

    # Preserve the portfolio hash to use below, when presenting the user with the public URL
    # for his portfolio.
    $portfolio_hash = $hash;
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
