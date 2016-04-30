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
define('SESSION_ID', 1);

# Is this the main session or a special session? As defined by Richmond Sunlight's database.
define('SESSION_SUFFIX', '');

# As defined by the GA LIS' database.
define('SESSION_LIS_ID', '161');

# As defined by the year.
define('SESSION_YEAR', 2016);

# Determine whether the GA is currently in session.
define('IN_SESSION', 'N');

# Set the FTP auth pair for legislative data.
define('LIS_FTP_USERNAME', 'janesmith');
define('LIS_FTP_PASSWORD', 's3cr3+p@ssw0rd');

# The DSN to connect to MySQL.
define('PDO_DSN', 'mysql:dbname=richmondsunlight;host=localhost;charset=utf8');
define('PDO_SERVER', 'localhost');
define('PDO_USERNAME', 'dbuser');
define('PDO_PASSWORD', 's3cr3+p@ssw0rd');

# Specify how to connect to Memcached.
define('MEMCACHED_SERVER', 'localhost');
define('MEMCACHED_PORT', '11211');

# The House Speaker's IDs. This is used in update_vote.php to translate votes credited to
# "H0000," which bizarrely indicates the speaker, to that legislator's ID, and in
# Video::identify_speakers to match the speaker to his identity. Here, H0046 and 41 indicate
# Bill Howell.
define('HOUSE_SPEAKER_LIS_ID', 'H0046');
define('HOUSE_SPEAKER_ID', '41');
	
# Set the directory to look to for cache data.
define('CACHE_DIR', '/vol/www/richmondsunlight.com/html/cache/');
	
# ESTABLISH API KEYS

# Google Maps
define('GMAPS_KEY', '');

# Yahoo App ID
define('YAHOO_KEY', '');

# Open States (Sunlight Foundation) API Key
define('OPENSTATES_KEY', '');

# Open Virginia / Virginia Decoded API Key
# (We're inconsistent in our nomenclature.)
define('OPENVA_KEY', '');
define('VA_DECODED_KEY', '');

# Mapbox API access ID and token
define('MAPBOX_ID', '');
define('MAPBOX_TOKEN', '');

# Pushover API Key
define('PUSHOVER_KEY', '');

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
