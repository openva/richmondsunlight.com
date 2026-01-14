<?php

/**
 * Compare current legislator shortnames against thumbnail filenames.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

$root = realpath(__DIR__ . '/../htdocs');
require $root . '/includes/settings.inc.php';
require $root . '/includes/class.Database.php';
require $root . '/includes/class.Legislator.php';
require $root . '/includes/class.Log.php';
require $root . '/includes/vendor/autoload.php';

$database = new Database();
$db = $database->connect_mysqli();
$log = new Log();

if ($db === false) {
    $log->put(
        message: 'Photo check aborted: database connection failed for host '
            . PDO_SERVER . ' database ' . MYSQL_DATABASE,
        level: 6
    );
    exit(1);
}

$legislator = new Legislator();
$legislator_list = $legislator->get_list('current');
if ($legislator_list === false) {
    $log->put(message: 'Photo check: no current legislators found.', level: 5);
    exit(0);
}

$shortnames = [];
foreach ($legislator_list as $entry) {
    if (empty($entry['shortname'])) {
        continue;
    }
    $shortnames[] = strtolower(trim($entry['shortname']));
}
$shortnames = array_values(array_unique($shortnames));

$thumbnail_dir = $root . '/images/legislators/thumbnails';
if (!is_dir($thumbnail_dir)) {
    $log->put(
        message: 'Photo check aborted: thumbnails directory missing: ' . $thumbnail_dir,
        level: 6
    );
    exit(1);
}

$thumbnail_shortnames = [];
$directory = new DirectoryIterator($thumbnail_dir);
foreach ($directory as $fileinfo) {
    if (!$fileinfo->isFile()) {
        continue;
    }
    $filename = $fileinfo->getFilename();
    if ($filename[0] === '.') {
        continue;
    }
    if (strtolower($fileinfo->getExtension()) !== 'jpg') {
        continue;
    }
    $thumbnail_shortnames[strtolower($fileinfo->getBasename('.jpg'))] = true;
}

$missing = array_values(array_diff($shortnames, array_keys($thumbnail_shortnames)));
sort($missing);

if (empty($missing)) {
    $log->put(message: 'Photo check: all current legislators have thumbnails.', level: 3);
    exit(0);
}

$log->put(
    message: 'Photo check: missing legislator thumbnails (' . count($missing) . ')',
    level: 5
);

foreach ($missing as $shortname) {
    $message = 'Missing legislator thumbnail: ' . $shortname . '.jpg';
    echo $message . "\n";
    $log->put(message: $message, level: 5);
}

exit(1);
