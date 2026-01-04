<?php

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
        message: 'Sitemap generation aborted: database connection failed for host '
            . PDO_SERVER . ' database ' . MYSQL_DATABASE,
        level: 6
    );
    exit(1);
}

/*
 * Sitemap XML header and footer, which we'll reuse repeatedly.
 */
$sitemap_xml_header = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
$sitemap_xml_footer = '</urlset>';

/*
 * If the sitemaps directory doesn't exist, create it.
 */
if (!file_exists(filename: '../htdocs/sitemaps')) {
    if (
        !mkdir(directory: '../htdocs/sitemaps', permissions: 0755, recursive: true)
        && !is_dir(filename: '../htdocs/sitemaps')
    ) {
        $log->put(message: 'Failed to create sitemaps directory', level: 6);
        exit(1);
    }
}

/*
 * List of sitemaps that we generated, to list in the master sitemap.xml.
 */
$sitemap_list = [];

/*
 * Fetch all representatives' shortnames to generate a sitemap
 */
$sql = 'SELECT
            people.shortname,
            DATE_FORMAT(terms.date_modified, "%Y-%m-%d") AS date_modified
        FROM people
        LEFT JOIN terms
            ON people.id = terms.person_id
        ORDER BY people.shortname ASC';
$result = mysqli_query(mysql: $GLOBALS['db'], query: $sql);
if ($result && mysqli_num_rows(result: $result) > 0) {
    $filename = '../htdocs/sitemaps/legislators.xml';

    // Create legislators.xml, if it doesn't already exist, or if it's old.
    if (file_exists(filename: $filename) === false || filemtime(filename: $filename) < strtotime(datetime: '-7 day')) {
        $sitemap_file = fopen(filename: $filename, mode: 'w');

        // Write XML header.
        fwrite(stream: $sitemap_file, data: $sitemap_xml_header . "\n");

        // Write each legislator's URL.
        while ($legislator = $result->fetch_assoc()) {
            fwrite(stream: $sitemap_file, data: '<url><loc>https://www.richmondsunlight.com/legislator/'
                . $legislator['shortname'] . '/</loc><lastmod>' . $legislator['date_modified']
                . '</lastmod></url>' . "\n");
        }

        // Write XML footer.
        fwrite(stream: $sitemap_file, data: $sitemap_xml_footer . "\n");

        fclose(stream: $sitemap_file);

        $log->put(message: 'Regenerated legislators sitemap', level: 3);
    }

    $sitemap_list[] = 'legislators.xml';
} else {
    if ($result === false) {
        $log->put(
            message: 'Legislator sitemap query failed: ' . mysqli_error(mysql: $GLOBALS['db']),
            level: 6
        );
    } else {
        $log->put(message: 'No legislators found for sitemap generation', level: 5);
    }
}

/*
 * Generate one bills sitemap per year
 */
for ($year = 2006; $year <= SESSION_YEAR; $year++) {
    /*
    * Fetch all bills to generate a sitemap
    *
    * This is a really expensive query (it takes on the order of 10 seconds), but this runs so
    * rarely that I'm not losing sleep over it.
    */
    $sql = 'SELECT bills.number,
                (SELECT date
                FROM bills_status
                WHERE bill_id=bills.id AND
                date IS NOT NULL
                ORDER BY date DESC
                LIMIT 1) AS date
            FROM bills
            LEFT JOIN sessions
                ON bills.session_id = sessions.id
            WHERE sessions.year = ' . $year . '
            ORDER BY year ASC, number ASC';
    $result = mysqli_query(mysql: $GLOBALS['db'], query: $sql);
    if ($result && mysqli_num_rows(result: $result) > 0) {
        $filename = '../htdocs/sitemaps/bills-' . $year . '.xml';
        // Create sitemap-bills-{year}.xml, if it doesn't already exist, or if it's from this year
        // and also old.
        if (
                file_exists(filename: $filename) === false
                ||
                $year == SESSION_YEAR && filemtime(filename: $filename) < strtotime(datetime: '-7 day')
        ) {
            $sitemap_file = fopen(filename: $filename, mode: 'w');

            // Write XML header.
            fwrite(stream: $sitemap_file, data: $sitemap_xml_header . "\n");

            // Write the URL each bill and its full text link.
            while ($bill = $result->fetch_assoc()) {
                fwrite(stream: $sitemap_file, data: '<url><loc>https://www.richmondsunlight.com/bill/'
                    . $year . '/' . $bill['number'] . '/</loc><lastmod>' . $bill['date'] . '</lastmod></url>' . "\n");
                fwrite(stream: $sitemap_file, data: '<url><loc>https://www.richmondsunlight.com/bill/'
                    . $year . '/' . $bill['number'] . '/fulltext/</loc></url>' . "\n");
            }

            // Write XML footer.
            fwrite(stream: $sitemap_file, data: $sitemap_xml_footer . "\n");

            fclose(stream: $sitemap_file);

            $log->put(message: 'Regenerated bills sitemap for ' . $year, level: 3);
        }
        $sitemap_list[] = 'bills-' . $year . '.xml';
    } else {
        if ($result === false) {
            $log->put(
                message: 'Bills sitemap query failed for year ' . $year . ': '
                    . mysqli_error(mysql: $GLOBALS['db']),
                level: 6
            );
            // Database error - exit entire script
            exit(1);
        } else {
            $log->put(message: 'No bills found for year ' . $year . ' when generating sitemap -- '
                . 'skipping this year', level: 4);
            // No bills for this year - skip and continue to next year
            continue;
        }
    }
}

/*
 * Create a master sitemap.xml that refers to all the other sitemaps.
 */
$filename = '../htdocs/sitemap.xml';
$sitemap_file = fopen(filename: $filename, mode: 'w');

$sitemap_index_header = '<?xml version="1.0" encoding="UTF-8"?>
    <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$sitemap_index_footer = '</sitemapindex>';

// Write XML header.
fwrite(stream: $sitemap_file, data: $sitemap_index_header . "\n");

foreach ($sitemap_list as $file) {
    fwrite(stream: $sitemap_file, data: '<sitemap>
        <loc>https://www.richmondsunlight.com/sitemaps/' . $file . '</loc>
    </sitemap>' . "\n");
}

// Write XML footer.
fwrite(stream: $sitemap_file, data: $sitemap_index_footer . "\n");

fclose(stream: $sitemap_file);
$log->put(
    message: 'Sitemap index updated with ' . count(value: $sitemap_list) . ' entries',
    level: 3
);
