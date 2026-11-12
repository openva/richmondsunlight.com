<?php

require __DIR__ . '/../htdocs/includes/settings.inc.php';
require __DIR__ . '/../htdocs/includes/class.Database.php';
require __DIR__ . '/../htdocs/includes/class.Legislator.php';
require __DIR__ . '/../htdocs/includes/class.Log.php';
require __DIR__ . '/../htdocs/includes/class.Places.php';
require __DIR__ . '/../htdocs/includes/class.Video.php';
require __DIR__ . '/../htdocs/includes/vendor/autoload.php';

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
 * Where the sitemaps go. These paths are relative to this script, not to the current directory,
 * which, when this is run during a deployment, is CodeDeploy's.
 */
$sitemap_dir = __DIR__ . '/../htdocs/sitemaps';
$sitemap_index = __DIR__ . '/../htdocs/sitemap.xml';

/*
 * If the sitemaps directory doesn't exist, create it.
 */
if (!file_exists(filename: $sitemap_dir)) {
    if (
        !mkdir(directory: $sitemap_dir, permissions: 0755, recursive: true)
        && !is_dir(filename: $sitemap_dir)
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
    $filename = $sitemap_dir . '/legislators.xml';

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
        $filename = $sitemap_dir . '/bills-' . $year . '.xml';
        // Create bills-{year}.xml, if it doesn't already exist, or if it's from this year or last
        // year (whose bills may have changed since it was last generated) and a day old.
        if (
                file_exists(filename: $filename) === false
                ||
                $year >= SESSION_YEAR - 1 && filemtime(filename: $filename) < strtotime(datetime: '-1 day')
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
 * Generate a sitemap of the places pages: the list of places, and every county and independent
 * city (but not towns) in the gazetteer.
 */
$sql = 'SELECT name
        FROM gazetteer
        WHERE municipality IS NULL OR municipality = CONCAT(name, " (city)")
        ORDER BY name ASC';
$result = mysqli_query(mysql: $GLOBALS['db'], query: $sql);
if ($result && mysqli_num_rows(result: $result) > 0) {
    $sitemap_file = fopen(filename: $sitemap_dir . '/places.xml', mode: 'w');
    fwrite(stream: $sitemap_file, data: $sitemap_xml_header . "\n");
    fwrite(stream: $sitemap_file, data: '<url><loc>https://www.richmondsunlight.com/places/</loc></url>' . "\n");
    while ($place = $result->fetch_assoc()) {
        fwrite(stream: $sitemap_file, data: '<url><loc>https://www.richmondsunlight.com/places/'
            . Places::slug($place['name']) . '/</loc></url>' . "\n");
    }
    fwrite(stream: $sitemap_file, data: $sitemap_xml_footer . "\n");
    fclose(stream: $sitemap_file);
    $sitemap_list[] = 'places.xml';
    $log->put(message: 'Regenerated places sitemap', level: 3);
} else {
    $log->put(
        message: 'Places sitemap query failed or found no places: ' . mysqli_error(mysql: $GLOBALS['db']),
        level: 5
    );
}

/*
 * Generate one sitemap of videos per year, from the API, with Google's video extensions
 * (https://developers.google.com/search/docs/crawling-indexing/sitemaps/video-sitemaps), so that
 * video of committee meetings, in particular, can be found.
 */
$video_xml_header = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
$xml = fn($text) => htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
for ($year = 2009; $year <= (int) date('Y'); $year++) {
    $filename = $sitemap_dir . '/videos-' . $year . '.xml';

    // Videos of this year or last year may still be being processed, so regenerate those daily.
    if (
        file_exists(filename: $filename)
        && ($year < (int) date('Y') - 1 || filemtime(filename: $filename) > strtotime(datetime: '-1 day'))
    ) {
        $sitemap_list[] = 'videos-' . $year . '.xml';
        continue;
    }

    $videos = Video::list_from_api(['year' => $year]);
    if (count($videos) === 0) {
        continue;
    }

    $sitemap_file = fopen(filename: $filename, mode: 'w');
    fwrite(stream: $sitemap_file, data: $video_xml_header . "\n");
    foreach ($videos as $video) {
        fwrite(stream: $sitemap_file, data: '<url><loc>' . $xml($video['url']) . '</loc>');

        // Google requires a thumbnail, and something to play.
        $mp4 = Video::media_url($video['path']);
        $youtube_id = Video::youtube_id(null, $video['path']);
        if ($video['status'] === 'available' && !empty($video['poster']) && ($mp4 || $youtube_id)) {
            $title = $video['meeting']['label'] . ', ' . date('F j, Y', strtotime($video['date']));
            fwrite(stream: $sitemap_file, data: '<video:video>'
                . '<video:thumbnail_loc>' . $xml($video['poster']) . '</video:thumbnail_loc>'
                . '<video:title>' . $xml($title) . '</video:title>'
                . '<video:description>' . $xml('Video of the ' . $video['meeting']['label'] . ' of the Virginia General Assembly, '
                    . date('F j, Y', strtotime($video['date'])) . '.') . '</video:description>'
                . ($mp4 ? '<video:content_loc>' . $xml($mp4) . '</video:content_loc>'
                    : '<video:player_loc>' . $xml('https://www.youtube.com/embed/' . $youtube_id) . '</video:player_loc>')
                . (!empty($video['length']) && $video['length'] <= 28800
                    ? '<video:duration>' . (int) $video['length'] . '</video:duration>' : '')
                . '<video:publication_date>' . $xml($video['date']) . '</video:publication_date>'
                . '</video:video>');
        }
        fwrite(stream: $sitemap_file, data: '</url>' . "\n");
    }
    fwrite(stream: $sitemap_file, data: $sitemap_xml_footer . "\n");
    fclose(stream: $sitemap_file);

    $sitemap_list[] = 'videos-' . $year . '.xml';
    $log->put(message: 'Regenerated videos sitemap for ' . $year, level: 3);
}

/*
 * Create a master sitemap.xml that refers to all the other sitemaps.
 */
$filename = $sitemap_index;
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
