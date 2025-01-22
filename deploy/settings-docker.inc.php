<?php

###
# Site Settings
#
# PURPOSE
# All the constants intended to be accessible throughout the site.
#
###

# THE CURRENT SESSION
# As defined by Richmond Sunlight's database
define('SESSION_ID', 30);

# Is this the main session or a special session? As defined by Richmond Sunlight's database.
define('SESSION_SUFFIX', '');

# As defined by the GA LIS' database.
define('SESSION_LIS_ID', '241');

# As defined by the year.
define('SESSION_YEAR', 2024);

# Start and end of this session.
define('SESSION_START', '2024-01-10');
define('SESSION_END', '2024-03-09');

# Set the FTP auth pair for legislative data.
define('LIS_FTP_USERNAME', '');
define('LIS_FTP_PASSWORD', '');

# The DSN to connect to MySQL.
define('PDO_DSN', 'mysql:host=db;dbname=richmondsunlight');
define('PDO_SERVER', 'db');
define('PDO_USERNAME', 'ricsun');
define('PDO_PASSWORD', 'password');
define('MYSQL_DATABASE', 'richmondsunlight');

# The API URL.
define('API_URL', 'http://api/');

# Specify how to connect to Memcached.
define('MEMCACHED_SERVER', '');
define('MEMCACHED_PORT', '');

# The House Speaker's IDs. This is used in update_vote.php to translate votes credited to
# "H0000," which bizarrely indicates the speaker, to that legislator's ID, and in
# Video::identify_speakers to match the speaker to her identity. Here, H322 and 455 indicate
# Don Scott.
define('HOUSE_SPEAKER_LIS_ID', 'H322');
define('HOUSE_SPEAKER_ID', '455');

# Set the directory to look to for cache data.
define('CACHE_DIR', '/vol/www/richmondsunlight.com/html/cache/');

# ESTABLISH API KEYS

# Google Maps
define('GMAPS_KEY', '');

# Open States (Sunlight Foundation) API Key
define('OPENSTATES_KEY', '');

# Open Virginia / Virginia Decoded API Key
# (We're inconsistent in our nomenclature.)
define('OPENVA_KEY', '');
define('VA_DECODED_KEY', '');

# Mapbox API access token
define('MAPBOX_TOKEN', '');

# LIS API token
define('LIS_KEY', '');

# Logging verbosity, on a scale of 1–8
define('LOG_VERBOSITY', 3);

# Slack API URL
define('SLACK_WEBHOOK', '');

# OpenAI API key
define('OPENAI_KEY', '');

# AWS auth info
# This is only used in some RS instances.
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET_KEY', '');

# The list of words that, when used, will lead to instant blacklisting. They're rot 13ed here.
$GLOBALS['banned_words'] = array('fuvg','shpx','nffubyr','chffl','phag','shpxre','zbgureshpxre',
    'shpxvat','pbpxfhpxre','gjng','qvpxurnq');
foreach ($GLOBALS['banned_words'] as &$word) {
    $word = str_rot13($word);
}

# Format money for the U.S.
setlocale(LC_MONETARY, 'en_US');

# Set the timezone.
date_default_timezone_set('America/New_York');

/*
 * Dynamically determine whether the legislature is in session and whether it's legislative season.
 */
if (
    time() >= strtotime(SESSION_START)
    &&
    time() <= strtotime(SESSION_END)
) {
    define('IN_SESSION', true);
} else {
    define('IN_SESSION', false);
}
if (date('n') >= 11 || date('n') <= 4) {
    define('LEGISLATIVE_SEASON', true);
} else {
    define('LEGISLATIVE_SEASON', false);
}
