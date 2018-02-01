<?php

###
# Log Out
#
# PURPOSE
# Destroys a session's cookies.
#
###

# INCLUDES
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# Start the session.
session_start();

# Delete the session from Memcached.
$mc = new Memcached();
$mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
$result = $mc->delete('user-session-' . $_SESSION['id']);

# Unset the user's hash and destroy the session. We go way over the top here, because of bug
# reports from folks maintaining multiple accounts, who have to hop back and forth a lot, and
# complain about logging out not working consistently.
$_SESSION['id'] = '';
$_SESSION['name'] = '';
$_SESSION['registered'] = '';
$_SESSION['portfolios'] = '';
$_SESSION = array();
session_destroy();

# Redirect the user back to the prior page or the homepage.
if (!empty($_SERVER['HTTP_REFERER']))
{
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
else
{
    header('Location: http://www.richmondsunlight.com/');
}
exit;
