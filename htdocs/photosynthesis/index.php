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

# Anybody with an account can keep portfolios. Everybody else is asked to log in (or make one).
$user = @get_user();
if (@logged_in() === false || !Portfolio::can_use($user)) {
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
    $public_url = SITE_BASE_URL . '/photosynthesis/' . $hash . '/';

    $page_body .= '
		<div class="portfolio" id="portfolio-' . $hash . '">
			<a name="' . $hash . '"></a>
			<div class="name">
				<h1>' . $name . '</h1>
				<div class="type">' . (($portfolio['type'] == 'smart') ? 'Smart ' : '') . 'Portfolio</div>
				<div class="rss"><a href="/photosynthesis/rss/portfolio/' . $hash . '/" title="Subscribe to this portfolio via RSS"><img src="/images/rss-icon.png" alt="RSS" /></a></div>
			</div>';

    # Who can see it, and where.
    if ($portfolio['public'] == 'y') {
        $page_body .= '
			<p class="visibility public">Public: anybody can see it at
				<a href="/photosynthesis/' . $hash . '/">' . $public_url . '</a>, and follow it with
				<a href="/photosynthesis/rss/portfolio/' . $hash . '/">its RSS feed</a>. Your notes on its
				bills are shown on the bills’ pages.</p>';
    } else {
        $page_body .= '
			<p class="visibility private">Private: only you can see it. (Its
				<a href="/photosynthesis/rss/portfolio/' . $hash . '/">RSS feed</a> works for anybody you
				give the address to.)</p>';
    }

    # A smart portfolio's criteria.
    if ($portfolio['type'] == 'smart') {
        $page_body .= '
			<p class="criteria">' . Portfolio::describe($portfolio['criteria'] ?? []) . '.</p>';
    }

    # Display the contents of the portfolio.
    $page_body .= show_portfolio($portfolio, $user['id']);

    $page_body .= '
			<div class="modify">
				<form method="post" action="/photosynthesis/process-actions.php" class="delete-portfolio"
					onsubmit="return confirm(' . Portfolio::text(json_encode('Are you sure you want to remove ' . $portfolio['name'] . '?')) . ')">
					' . Csrf::field() . '
					<input type="hidden" name="delete-portfolio" value="' . $hash . '" />
					<button type="submit" title="Stop tracking these bills">delete</button>
				</form>
				<a href="/photosynthesis/portfolios/edit/' . $hash . '/" title="Modify this portfolio">edit</a> &nbsp;
			</div>
			<div>' . Portfolio::notes_html($portfolio['notes']) . '</div>
		</div>';
}

# Making more portfolios, normal and smart.
$page_body .= '
	<div id="create-portfolios" class="tabs">
		<h1>Create a Portfolio</h1>
		<ul id="create-portfolios">
			<li><a href="#create-portfolio">Portfolio</a></li>
			<li><a href="#create-smart-portfolio">Smart Portfolio</a></li>
		</ul>

		<div id="create-portfolio">
			<p>A list of bills that you choose.</p>
			' . @portfolio_form() . '
		</div>

		<div id="create-smart-portfolio">
			<p>A list that fills itself, with every bill that matches what you choose here, as bills
			are filed, tagged, and referred.</p>
			' . @smart_portfolio_form() . '
		</div>
	</div>';

$page_body .= '
	<p class="preferences"><a href="/photosynthesis/preferences/">Your name, e-mail address, password,
	and e-mail updates</a></p>';

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
