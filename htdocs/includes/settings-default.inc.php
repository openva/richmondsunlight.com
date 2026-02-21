<?php

###
# Site Settings
#
# PURPOSE
# All the constants intended to be accessible throughout the site.
#
###

if (!function_exists('rs_define')) {
    /**
     * Define a constant unless it was already provided (e.g., via local overrides).
     */
    function rs_define(string $name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

$rs_local_settings_file = __DIR__ . '/settings.local.inc.php';
if (is_readable($rs_local_settings_file)) {
    require $rs_local_settings_file;
}

# THE CURRENT SESSION
# As defined by Richmond Sunlight's database
rs_define('SESSION_ID', 32);

# Is this the main session or a special session? As defined by Richmond Sunlight's database.
rs_define('SESSION_SUFFIX', '');

# As defined by the GA LIS' database, based on the year.
rs_define('SESSION_LIS_ID', '261');

# As defined by the GA LIS' database, based on an internal ID.
rs_define('SESSION_LIS_API_ID', '59');

# As defined by the year.
rs_define('SESSION_YEAR', 2026);

# Start and end of this session.
rs_define('SESSION_START', '2026-01-14');
rs_define('SESSION_END', '2026-03-15');

# ENVIRONMENT
# Set to false for Docker/development, true for production
rs_define('IS_PRODUCTION', true);

# Base URL for this site (used in redirects, canonical links, etc.)
if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $rs_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    rs_define('SITE_BASE_URL', $rs_scheme . '://' . $_SERVER['HTTP_HOST']);
    unset($rs_scheme);
} else {
    rs_define('SITE_BASE_URL', 'https://www.richmondsunlight.com');
}

# Set the FTP auth pair for legislative data.
rs_define('LIS_FTP_USERNAME', '');
rs_define('LIS_FTP_PASSWORD', '');

# The DSN to connect to MySQL.
rs_define('PDO_DSN', '');
rs_define('PDO_SERVER', '');
rs_define('PDO_USERNAME', '');
rs_define('PDO_PASSWORD', '');
rs_define('MYSQL_DATABASE', '');

# The API URL.
rs_define('API_URL', '');

# Specify how to connect to Memcached.
rs_define('MEMCACHED_SERVER', '');
rs_define('MEMCACHED_PORT', '11211');

# Configure PHP sessions to use Memcached
ini_set('session.save_handler', 'memcached');
ini_set('session.save_path', MEMCACHED_SERVER . ':' . MEMCACHED_PORT);

# The House Speaker's IDs. This is used in update_vote.php to translate votes credited to
# "H0000," which bizarrely indicates the speaker, to that legislator's ID, and in
# Video::identify_speakers to match the speaker to her identity. Here, H322 and 455 indicate
# Don Scott.
rs_define('HOUSE_SPEAKER_LIS_ID', 'H322');
rs_define('HOUSE_SPEAKER_ID', '455');

# Set the directory to look to for cache data.
rs_define('CACHE_DIR', sys_get_temp_dir());

# ESTABLISH API KEYS

# Google Maps
rs_define('GMAPS_KEY', '');

# Open States API Key
rs_define('OPENSTATES_KEY', '');

# Open Virginia / Virginia Decoded API Key
# (We're inconsistent in our nomenclature.)
rs_define('OPENVA_KEY', '');
rs_define('VA_DECODED_KEY', '');

# Mapbox API access token
rs_define('MAPBOX_TOKEN', '');

# LIS API token
rs_define('LIS_KEY', '');

# Logging verbosity, on a scale of 1–8
rs_define('LOG_VERBOSITY', 3);

# Slack API URL
rs_define('SLACK_WEBHOOK', '');

# YouTube API key
rs_define('YOUTUBE_API_KEY', '');

# OpenAI API key
rs_define('OPENAI_KEY', '');

# Internet Archive S3-style auth info
rs_define('IA_ACCESS_KEY', '');
rs_define('IA_SECRET_KEY', '');

# AWS auth info
rs_define('AWS_REGION', 'us-east-1');
rs_define('AWS_ACCESS_KEY', '');
rs_define('AWS_SECRET_KEY', '');

# Video SQS URL
rs_define('VIDEO_SQS_URL', 'https://sqs.us-east-1.amazonaws.com/947603853016/rs-video-harvester.fifo');

# Define the browser used to get cookies for yt-dlp
define('YTDLP_COOKIES_BROWSER', 'chrome');

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
