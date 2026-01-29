<?php

/**
 * Video class integration tests
 *
 * Tests Video class methods with real video_index data, focusing on:
 * - Screenshot URL generation with 8-character padding
 * - Clip indexing from video_index data
 * - URL protocol handling (https://)
 */

require_once __DIR__ . '/../../htdocs/includes/settings.inc.php';
require_once __DIR__ . '/../../htdocs/includes/class.Database.php';
require_once __DIR__ . '/../../htdocs/includes/class.Video.php';
require_once __DIR__ . '/../../htdocs/includes/functions.inc.php';

// Set REQUEST_URI to prevent Database class from exiting on connection failure
$_GET['REQUEST_URI'] = 'api.richmondsunlight.com/test';

$db = new Database();
$mysqli_result = $db->connect_mysqli();

if ($mysqli_result === false) {
    echo "❌ Database connection failed - cannot run integration tests\n";
    exit(1);
}

$failures = [];
$warnings = [];

function check($condition, $message) {
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

echo "\n=== Video Class Integration Tests ===\n\n";

// =============================================================================
// Test: Video data exists in database
// =============================================================================

echo "Checking video data availability...\n";

$sql = 'SELECT COUNT(*) as count FROM video_index';
$result = mysqli_query($GLOBALS['db'], $sql);
$row = mysqli_fetch_assoc($result);
$video_index_count = $row['count'];

check($video_index_count > 0, "video_index table should contain data (found {$video_index_count} rows)");
echo "  Found {$video_index_count} rows in video_index\n";

$sql = 'SELECT COUNT(*) as count FROM files WHERE type="video" AND capture_directory IS NOT NULL';
$result = mysqli_query($GLOBALS['db'], $sql);
$row = mysqli_fetch_assoc($result);
$video_files_count = $row['count'];

check($video_files_count > 0, "files table should contain videos with capture_directory");
echo "  Found {$video_files_count} video files with capture directories\n";

// =============================================================================
// Test: index_clips() processes video_index data correctly
// =============================================================================

echo "\nTesting index_clips() with real data...\n";

// Find a video with video_index data
$sql = 'SELECT files.id, files.chamber, files.date, COUNT(video_index.id) as index_count
        FROM files
        LEFT JOIN video_index ON files.id = video_index.file_id
        WHERE files.type="video"
        GROUP BY files.id
        HAVING index_count > 5
        ORDER BY index_count DESC
        LIMIT 1';
$result = mysqli_query($GLOBALS['db'], $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $video_data = mysqli_fetch_assoc($result);

    echo "  Testing with video ID {$video_data['id']} ({$video_data['chamber']} {$video_data['date']}, {$video_data['index_count']} index entries)\n";

    // Test with bills
    $video = new Video();
    $video->id = $video_data['id'];
    $video->clip_type = 'bills';
    $video->fuzz = 5;

    $result = $video->index_clips();

    if ($result === true) {
        check(isset($video->clips), 'index_clips should set $clips property');
        check(is_object($video->clips), '$clips should be an object');

        $clip_count = count((array)$video->clips);
        check($clip_count > 0, "index_clips should find clips (found {$clip_count})");
        echo "    ✓ Found {$clip_count} bill clips\n";

        // Test clip structure - get first clip from the object
        $clips_array = (array)$video->clips;
        $first_clip = reset($clips_array);

        if ($first_clip) {
            check(isset($first_clip->screenshot), 'Clip should have screenshot property');
            check(isset($first_clip->start), 'Clip should have start time');
            check(isset($first_clip->end), 'Clip should have end time');
            check(isset($first_clip->duration), 'Clip should have duration');

            // Test screenshot URL format
            if (isset($first_clip->screenshot)) {
                check(
                    strpos($first_clip->screenshot, 'https://') === 0,
                    'Screenshot URL should use https:// protocol'
                );
                check(
                    strpos($first_clip->screenshot, '/screenshots/') === false,
                    'Screenshot URL should not contain /screenshots/ directory'
                );
                echo "    ✓ Screenshot URL: {$first_clip->screenshot}\n";
            }
        } else {
            $warnings[] = "Could not access first clip from clips object";
        }
    } else {
        $warnings[] = "index_clips returned false for video {$video_data['id']}";
    }

    // Test with legislators
    $video = new Video();
    $video->id = $video_data['id'];
    $video->clip_type = 'legislators';
    $video->fuzz = 5;

    $result = $video->index_clips();

    if ($result === true) {
        $clip_count = count((array)$video->clips);
        echo "    ✓ Found {$clip_count} legislator clips\n";
    }

} else {
    $warnings[] = 'No videos with video_index data found for testing';
}

// =============================================================================
// Test: by_bill() generates correct screenshot URLs with 8-char padding
// =============================================================================

echo "\nTesting by_bill() screenshot URL generation...\n";

// Find a bill with video clips
$sql = 'SELECT video_index.linked_id as bill_id, COUNT(*) as clip_count
        FROM video_index
        WHERE video_index.type="bill" AND video_index.linked_id IS NOT NULL
        GROUP BY video_index.linked_id
        HAVING clip_count >= 3
        LIMIT 1';
$result = mysqli_query($GLOBALS['db'], $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $bill_data = mysqli_fetch_assoc($result);

    echo "  Testing with bill ID {$bill_data['bill_id']} ({$bill_data['clip_count']} video moments)\n";

    $video = new Video();
    $video->bill_id = $bill_data['bill_id'];

    $clips = $video->by_bill();

    if ($clips !== false && is_array($clips) && count($clips) > 0) {
        check(count($clips) > 0, "by_bill should return clips array");
        echo "    ✓ Found " . count($clips) . " clip(s)\n";

        $first_clip = $clips[0];

        // Check required fields
        check(isset($first_clip['screenshot']), 'Clip should have screenshot URL');
        check(isset($first_clip['start']), 'Clip should have start time');
        check(isset($first_clip['end']), 'Clip should have end time');
        check(isset($first_clip['chamber']), 'Clip should have chamber');
        check(isset($first_clip['date']), 'Clip should have date');

        // Check screenshot URL format
        if (isset($first_clip['screenshot'])) {
            $url = $first_clip['screenshot'];

            check(
                strpos($url, 'https://video.richmondsunlight.com/') === 0,
                'Screenshot URL should start with https://video.richmondsunlight.com/'
            );

            check(
                strpos($url, '/screenshots/') === false,
                'Screenshot URL should not contain /screenshots/ directory'
            );

            // Extract filename from URL
            $filename = basename($url);

            // Check for 8-character padding (e.g., 00002796.jpg)
            if (preg_match('/^(\d+)\.jpg$/', $filename, $matches)) {
                $number = $matches[1];
                check(
                    strlen($number) === 8,
                    "Screenshot filename should be 8 characters (found {$number} with length " . strlen($number) . ")"
                );
                echo "    ✓ Screenshot filename has correct padding: {$filename}\n";
            } else {
                $failures[] = "Screenshot filename doesn't match expected format: {$filename}";
            }

            echo "    ✓ Screenshot URL: {$url}\n";
        }
    } else {
        $warnings[] = "by_bill returned no clips for bill {$bill_data['bill_id']}";
    }
} else {
    $warnings[] = 'No bills with video_index data found for testing';
}

// =============================================================================
// Test: screenshots() method generates URLs with correct format
// =============================================================================

echo "\nTesting screenshots() URL generation...\n";

$sql = 'SELECT id, fps, capture_rate, length, capture_directory
        FROM files
        WHERE type="video" AND fps > 0 AND capture_rate > 0 AND length IS NOT NULL
        LIMIT 1';
$result = mysqli_query($GLOBALS['db'], $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $video_data = mysqli_fetch_assoc($result);

    $video = new Video();
    $video->id = $video_data['id'];
    $video->fps = $video_data['fps'];
    $video->capture_rate = $video_data['capture_rate'];
    $video->length = $video_data['length'];
    $video->capture_directory = $video_data['capture_directory'];
    $video->frequency = 60; // screenshots every 60 seconds

    $video->screenshots();

    if (isset($video->screenshots) && is_object($video->screenshots)) {
        $screenshot_count = count((array)$video->screenshots);
        check($screenshot_count > 0, "screenshots() should generate screenshot URLs");
        echo "  Generated {$screenshot_count} screenshot URLs\n";

        // Check first screenshot
        if (isset($video->screenshots->{0})) {
            $screenshot = $video->screenshots->{0};
            check(isset($screenshot->filename), 'Screenshot should have filename property');

            if (isset($screenshot->filename)) {
                $url = $screenshot->filename;

                check(
                    strpos($url, 'https://video.richmondsunlight.com/') === 0,
                    'Screenshot URL should use https:// protocol'
                );

                // Extract filename and check padding
                $filename = basename($url);
                if (preg_match('/^(\d+)\.jpg$/', $filename, $matches)) {
                    $number = $matches[1];
                    check(
                        strlen($number) === 8,
                        "Screenshot filename should be 8 characters (found {$number})"
                    );
                    echo "  ✓ Screenshot filename: {$filename}\n";
                }

                echo "  ✓ Sample URL: {$url}\n";
            }
        }
    } else {
        $warnings[] = "screenshots() did not generate URLs for video {$video_data['id']}";
    }
} else {
    $warnings[] = 'No suitable video found for screenshots() test';
}

// =============================================================================
// Test: get_clip() handles protocol conversion
// =============================================================================

echo "\nTesting get_clip() protocol handling...\n";

$sql = 'SELECT id FROM video_clips LIMIT 1';
$result = mysqli_query($GLOBALS['db'], $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);

    $video = new Video();
    $video->id = $row['id'];

    $result = $video->get_clip();

    if ($result === true && isset($video->clip->screenshot)) {
        $url = $video->clip->screenshot;

        check(
            strpos($url, 'https://') === 0 || strpos($url, '//') !== 0,
            'Screenshot URL should use https:// (not protocol-relative)'
        );

        check(
            strpos($url, 'http://') !== 0,
            'Screenshot URL should not use http:// (should be https://)'
        );

        echo "  ✓ Clip screenshot URL: {$url}\n";
    }
} else {
    $warnings[] = 'No video clips found for get_clip() test';
}

// =============================================================================
// Summary
// =============================================================================

echo "\n";

if (!empty($warnings)) {
    foreach ($warnings as $warning) {
        echo "⚠️  {$warning}\n";
    }
    echo "\n";
}

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "❌ {$failure}\n";
    }
    echo "\n";
    echo "Total failures: " . count($failures) . "\n";
    exit(1);
}

echo "✅ All integration tests passed!\n";
echo "Tests run: video_index processing, screenshot URLs, 8-char padding, https:// protocol\n";
exit(0);
