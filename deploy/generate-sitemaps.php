<?php

require '../htdocs/includes/settings.inc.php';
require '../htdocs/includes/class.Database.php';
require '../htdocs/includes/class.Legislator.php';
require '../htdocs/includes/class.Log.php';
require '../htdocs/includes/vendor/autoload.php';

$database = new Database();
$db = $database->connect_mysqli();
$log = new Log();

/*
 * Sitemap XML header and footer, which we'll reuse repeatedly.
 */
$sitemap_xml_header = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
$sitemap_xml_footer = '</urlset>';

/*
 * If the sitemaps directory doesn't exist, create it.
 */
if (!file_exists('../htdocs/sitemaps')) {
    mkdir('../htdocs/sitemaps', 0755, true);
}

/*
 * List of sitemaps that we generated, to list in the master sitemap.xml.
 */
$sitemap_list = [];

/*
 * Fetch all representatives' shortnames to generate a sitemap
 */
$sql = 'SELECT shortname
        FROM representatives
        ORDER BY shortname ASC';
$result = mysqli_query($GLOBALS['db'], $sql);
if (mysqli_num_rows($result) > 0) {
    $filename = '../htdocs/sitemaps/legislators.xml';

    // Create legislators.xml, if it doesn't already exist, or if it's old.
    if (file_exists($filename) === false || filemtime($filename) < strtotime('-7 day')) {
        $sitemap_file = fopen($filename, 'w');

        // Write XML header.
        fwrite($sitemap_file, $sitemap_xml_header . "\n");

        // Write each legislator's URL.
        while ($row = $result->fetch_assoc()) {
            fwrite($sitemap_file, '<url><loc>https://www.richmondsunlight.com/legislator/'
                . $row['shortname'] . '/</loc></url>' . "\n");
        }

        // Write XML footer.
        fwrite($sitemap_file, $sitemap_xml_footer . "\n");

        $log->put(message: 'Regenerated legislators sitemap', level: 3);
    }

    // Append this to our list
    $sitemap_list[] = 'legislators.xml';
} else {
    $log->put(message: 'No legislators found for sitemap generation', level: 5);
}

/*
 * Generate one bills sitemap per year
 */
for ($year = 2006; $year <= SESSION_YEAR; $year++) {
    /*
    * Fetch all bills to generate a sitemap
    */
    $sql = 'SELECT bills.number
            FROM bills
            LEFT JOIN sessions
                ON bills.session_id = sessions.id
            WHERE sessions.year = ' . $year . '
            ORDER BY year ASC, number ASC';
    $result = mysqli_query($GLOBALS['db'], $sql);
    if (mysqli_num_rows($result) > 0) {
        $filename = '../htdocs/sitemaps/bills-' . $year . '.xml';
        // Create sitemap-bills-{year}.xml, if it doesn't already exist, or if it's from this year
        // and also old.
        if (
                file_exists($filename) === false
                ||
                $year == SESSION_YEAR && filemtime($filename) < strtotime('-7 day')
        ) {
            $sitemap_file = fopen($filename, 'w');

            // Write XML header.
            fwrite($sitemap_file, $sitemap_xml_header . "\n");

            // Write each bill's URL.
            while ($row = $result->fetch_assoc()) {
                fwrite($sitemap_file, '<url><loc>https://www.richmondsunlight.com/bill/' . $year
                    . '/' . $row['number'] . '/</loc></url>' . "\n");
            }

            // Write XML footer.
            fwrite($sitemap_file, $sitemap_xml_footer . "\n");

            $log->put(message: 'Regenerated bills sitemap for ' . $year, level: 3);
        }
        // Append this to our list
        $sitemap_list[] = 'bills-' . $year . '.xml';
    } else {
        $log->put(message: 'No bills found for year ' . $year . ' when generating sitemap', level: 4);
    }
}

/*
 * Create a master sitemap.xml that refers to all the other sitemaps.
 */
$filename = '../htdocs/sitemap.xml';
$sitemap_file = fopen($filename, 'w');

$sitemap_index_header = '<?xml version="1.0" encoding="UTF-8"?>
    <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$sitemap_index_footer = '</sitemapindex>';

// Write XML header.
fwrite($sitemap_file, $sitemap_index_header . "\n");

foreach ($sitemap_list as $file) {
    fwrite($sitemap_file, '<sitemap>
        <loc>https://www.richmondsunlight.com/sitemaps/' . $file . '</loc>
    </sitemap>' . "\n");
}

// Write XML footer.
fwrite($sitemap_file, $sitemap_index_footer . "\n");

fclose($sitemap_file);
$log->put(message: 'Sitemap index updated with ' . count(value: $sitemap_list) . ' entries',
    level: 3);
