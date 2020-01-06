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
define('SESSION_ID', 23);

# Is this the main session or a special session? As defined by Richmond Sunlight's database.
define('SESSION_SUFFIX', '');

# As defined by the GA LIS' database.
define('SESSION_LIS_ID', '201');

# As defined by the year.
define('SESSION_YEAR', 2020);

# Determine whether the GA is currently in session.
define('IN_SESSION', 'Y');

# Set the FTP auth pair for legislative data.
define('LIS_FTP_USERNAME', '');
define('LIS_FTP_PASSWORD', '');

# The DSN to connect to MySQL.
define('PDO_DSN', '');
define('PDO_SERVER', '');
define('PDO_USERNAME', '');
define('PDO_PASSWORD', '');
define('MYSQL_DATABASE', '');

# The API URL.
define('API_URL', '');

# Specify how to connect to Memcached.
define('MEMCACHED_SERVER', '');
define('MEMCACHED_PORT', '11211');

# The House Speaker's IDs. This is used in update_vote.php to translate votes credited to
# "H0000," which bizarrely indicates the speaker, to that legislator's ID, and in
# Video::identify_speakers to match the speaker to her identity. Here, H229 and 349 indicate
# Eileen Filler-Corn.
define('HOUSE_SPEAKER_LIS_ID', 'H229');
define('HOUSE_SPEAKER_ID', '349');

# Set the directory to look to for cache data.
define('CACHE_DIR', '/vol/www/richmondsunlight.com/html/cache/');

# ESTABLISH API KEYS

# Google Maps
define('GMAPS_KEY', '');

# Geoparser.io API key
define('GEOPARSER_KEY', '');

# Open Virginia / Virginia Decoded API Key
# (We're inconsistent in our nomenclature.)
define('OPENVA_KEY', '');
define('VA_DECODED_KEY', '');

# Mapbox API access token
define('MAPBOX_TOKEN', '');

# Akismet API access token
define('AKISMET_KEY', '');

# Logging verbosity, on a scale of 1–8
define('LOG_VERBOSITY', 3);

# Slack API URL
define('SLACK_WEBHOOK', '');

# Pushover API key
define('PUSHOVER_KEY', '');

# AWS auth info
# This is only used in some RS instances.
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET_KEY', '');

# The list of words that, when used, will lead to instant blacklisting. They're rot 13ed here.
$GLOBALS['banned_words'] = array('fuvg','shpx','nffubyr','chffl','phag','shpxre','zbgureshpxre',
    'shpxvat','pbpxfhpxre','gjng','qvpxurnq');
foreach ($GLOBALS['banned_words'] as &$word)
{
    $word = str_rot13($word);
}

# Format money for the U.S.
setlocale(LC_MONETARY, 'en_US');

# Set the timezone.
date_default_timezone_set('America/New_York');
