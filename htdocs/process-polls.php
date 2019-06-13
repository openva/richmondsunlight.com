<?php

###
# Accept Poll Votes
#
# PURPOSE
# Receives submitted votesand adds them to the database, determining
# whether they're likely to be spam or require authentication.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'includes/settings.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_mysqli();

# INITIALIZE SESSION
session_start();

# LOCALIZE VARIABLES
$poll = $_REQUEST['poll'];

# CHECK FOR SPAMMERS
# If the third, DIV-hidden poll option is selected, we know it's a spammer, so just bail.
if ($poll['vote'] == 'x')
{
    die();
}
if (mb_strlen($_SERVER['HTTP_USER_AGENT']) <= 1)
{
    die();
}
if (mb_stristr($_SERVER['HTTP_USER_AGENT'], 'curl') === TRUE)
{
    die();
}
if (mb_stristr($_SERVER['HTTP_USER_AGENT'], 'Wget') === TRUE)
{
    die();
}

# REJECT MISSING POLL VOTES
if (empty($poll['vote']))
{
    header("Location: https://$_SERVER[SERVER_NAME]$poll[return_to]");
    exit;
}
if (!logged_in())
{
    create_user();
}

if (!empty($_SESSION['id']))
{

    # ASSEMBLE THE INSERTION SQL
    $sql = 'INSERT INTO polls
			SET bill_id=' . $poll['bill_id'] . ', vote="' . $poll['vote'] . '",
			ip="' . $_SERVER['REMOTE_ADDR'] . '", user_id=
				(SELECT id
				FROM users
				WHERE cookie_hash = "' . $_SESSION['id'] . '"),
			date_created=now()';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (!$result)
    {
        die("Poll vote could not be cast.");
    }

    /*
     * Delete the cache.
     */
    if (MEMCACHED_SERVER != '')
    {
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $mc->delete('poll-' . $poll['bill_id']);
    }

    # If the insert was successful, redirect the user back to the page of
    # origin.
    if (!empty($poll['return_to']))
    {
        header("Location: https://$_SERVER[SERVER_NAME]$poll[return_to]");
        exit;
    }
}
