<?php

###
# Photosynthesis Unsubscribe
#
# PURPOSE
# Stops portfolio email, from the link at the bottom of each message
# (/photosynthesis/unsubscribe/{unsub_hash}/). Following the link asks; the button (a POST) stops
# it. Mail programs' one-click unsubscribing (the message's List-Unsubscribe-Post header) is a POST
# to the same address, so it stops it at once. Email can be turned back on in preferences.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once '../includes/functions.inc.php';
include_once '../includes/settings.inc.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database();
$database->connect_mysqli();

# PAGE METADATA
$page_title = 'Photosynthesis » Unsubscribe';
$site_section = 'photosynthesis';

# The link's hash is the user's dashboard_user_data.unsub_hash. Nothing else is needed to stop
# their email: the link is in the messages sent to them.
$hash = (string) ($_GET['hash'] ?? '');
$subscribed = PortfolioDigest::subscribed($hash);

if ($subscribed === null) {
    http_response_code(404);
    $page_body = '<p>Sorry, but that unsubscribe link doesn’t work. To stop getting email about your
        portfolios, <a href="/photosynthesis/preferences/">change your preferences</a>.</p>';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = PortfolioDigest::unsubscribe($hash);

    # get_user() keeps a copy of the user's record for an hour.
    if (MEMCACHED_SERVER != '' && class_exists('Memcached')) {
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $mc->delete('user-' . $user_id);
    }

    $page_body = '<div id="messages" class="updated">You won’t get any more email about your portfolios.</div>
        <p>If you change your mind, you can turn it back on in
        <a href="/photosynthesis/preferences/">your preferences</a>.</p>';
} elseif ($subscribed === false) {
    $page_body = '<p>You’re already unsubscribed: you don’t get email about your portfolios. You can
        turn it back on in <a href="/photosynthesis/preferences/">your preferences</a>.</p>';
} else {
    $page_body = '<form method="post" action="/photosynthesis/unsubscribe/' . htmlspecialchars($hash) . '/" id="unsubscribe">
            <p>Stop sending you email about your portfolios? (You can turn it back on in
            <a href="/photosynthesis/preferences/">your preferences</a>.)</p>
            <input type="submit" value="Stop sending me email" />
        </form>';
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
