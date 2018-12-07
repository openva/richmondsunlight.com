<?php

###
# Accept Tag Additions via Ajax
#
# PURPOSE
# Receives submitted tags, adds them, and returns the user back to the
# bill page.
#
###

# INCLUDES
# Include any files or libraries that are necessary for this specific
# page to function.
include_once 'settings.inc.php';
include_once 'functions.inc.php';
include_once 'vendor/autoload.php';

# DECLARATIVE FUNCTIONS
# Run those functions that are necessary prior to loading this specific
# page.
$database = new Database;
$database->connect_old();

# INITIALIZE SESSION
session_start();

# Grab the user data.
$user = get_user();

# LOCALIZE VARIABLES
$tags = $_POST['tags'];
if (isset($_POST['delete']))
{
    $delete = $_POST['delete'];
}
if (!isset($_POST['bill_id']))
{
    header('HTTP/1.0 500 Internal Server Error');
    $message = array('error' => 'No tags provided.');
    echo json_encode($message);
    exit();
}
$bill_id = $_POST['bill_id'];

# REJECT MISSING TAGS
if (empty($tags))
{

    # But if the tags are missing because a trusted user is deleting one, that's fine.
    if (!empty($delete) && ($user['trusted'] == 'y'))
    {

        # Delete the tag.
        $sql = 'DELETE FROM tags
				WHERE id=' . $delete;
        mysql_query($sql);

        # Delete the bill from Memcached.
        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $result = $mc->delete('bill-' . $bill_id);

        header('HTTP/1.0 201 Created');
        exit();
    }

    header('HTTP/1.0 500 Internal Server Error');
    $message = array('error' => 'No tags provided.');
    echo json_encode($message);
    exit();
}

# BAR SPAMMERS
if (mb_strlen($_SERVER['HTTP_USER_AGENT']) <= 1)
{
    exit();
}
if (mb_stristr($_SERVER['HTTP_USER_AGENT'], 'curl') === TRUE)
{
    exit();
}
if (mb_stristr($_SERVER['HTTP_USER_AGENT'], 'Wget') === TRUE)
{
    exit();
}

# Create a user record.
if (!logged_in())
{
    create_user();
}

if (!empty($_SESSION['id']))
{// && !blacklisted())

    # Explode the tags into an array to be inserted individually.
    $tag = explode(',', $tags);

    for ($i=0; $i<count($tag); $i++)
    {
        $tag[$i] = mb_strtolower(trim($tag[$i]));

        # Check the tag against the dirty words.
        if (in_array(str_rot13($tag[$i]), $GLOBALS['banned_words']))
        {
            header('HTTP/1.0 403 Forbidden');
            $message = array('error' => 'Tag prohibited.');
            echo json_encode($message);
            exit();
        }

        # Drop useless tags.
        if ($tag[$i] === '1')
        {
            continue;
        }

        # Don't proceed if it's blank.
        if (!empty($tag[$i]))
        {

            # Make sure it's safe.
            $tag[$i] = preg_replace("/[[:punct:]]/D", '', $tag[$i]);
            $tag[$i] = trim(mysql_real_escape_string($tag[$i]));

            # Check one more time to make sure it's not empty.
            if (!empty($tag[$i]))
            {

                # Assemble the insertion SQL
                $sql = 'INSERT INTO tags
						SET bill_id=' . $bill_id . ', tag="' . $tag[$i] . '",
						ip="' . $_SERVER['REMOTE_ADDR'] . '", user_id=
							(SELECT id
							FROM users
							WHERE cookie_hash = "' . $_SESSION['id'] . '"),
						date_created=now()';
                $result = mysql_query($sql);

                /*
                 * If there was a database-insertion error.
                 */
                if (!$result)
                {
                    header('HTTP/1.0 500 Internal Server Error');
                    $message = array('error' => 'Tags could not be saved.');
                    echo json_encode($message);
                    exit();
                }
            }
        }
    }


    # Delete the bill from Memcached.
    $mc = new Memcached();
    $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
    $result = $mc->delete('bill-' . $tags['bill_id']);

    $log = new Log;
    $result = $log->put('New tags added: ' . implode(', ', $tag) . $_SERVER['HTTP_REFERER'], 3);

    /*
     * Send a 201 Created HTTP header, to indicate success.
     */
    header('HTTP/1.0 201 Created');
    exit();
}

# If the user didn't accept a cookie or is blacklisted.
else
{
    header('HTTP/1.0 403 Forbidden');
    $message = array('error' => 'You do not have permission to add tags.');
    echo json_encode($message);
    exit();
}
