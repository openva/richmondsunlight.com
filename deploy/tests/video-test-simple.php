<?php
echo "START OF SCRIPT\n";

@ini_set('display_errors', '0');
@error_reporting(E_ERROR);

require_once __DIR__ . '/../../htdocs/includes/settings.inc.php';
echo "Settings loaded\n";

require_once __DIR__ . '/../../htdocs/includes/class.Database.php';
echo "Database class loaded\n";

require_once __DIR__ . '/../../htdocs/includes/class.Video.php';
echo "Video class loaded\n";

require_once __DIR__ . '/../../htdocs/includes/functions.inc.php';
echo "Functions loaded\n";

echo "Testing if Video class exists: " . (class_exists('Video') ? 'YES' : 'NO') . "\n";

echo "END OF SCRIPT\n";
