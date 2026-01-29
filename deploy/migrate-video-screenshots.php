#!/usr/bin/env php
<?php

/**
 * Video Screenshot Migration Script
 *
 * Migrates video screenshots on S3 from old structure to new structure:
 * - Old: /{chamber}/{date}/screenshots/full/XXXXX.jpg
 * - New: /{chamber}/{date}/XXXXXXXX.jpg (8-char padded)
 * - Old: /{chamber}/{date}/screenshots/thumbnail/XXXXX.jpg
 * - New: /{chamber}/{date}/XXXXXXXX-thumbnail.jpg
 *
 * Usage:
 *   php migrate-video-screenshots.php --dry-run     # Test without changes
 *   php migrate-video-screenshots.php --execute     # Perform migration (copy files)
 *   php migrate-video-screenshots.php --verify-only # Verify migration results
 *   php migrate-video-screenshots.php --cleanup     # Delete old files after verification
 *
 * IMPORTANT: Run --execute first, verify results, then run --cleanup after 30 days
 */

// Includes
require_once __DIR__ . '/../htdocs/includes/settings.inc.php';
require_once __DIR__ . '/../htdocs/includes/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Constants
define('S3_BUCKET', 'video.richmondsunlight.com');
define('LOG_FILE', __DIR__ . '/screenshot-migration.log');
define('MAX_RETRIES', 3);

/**
 * Main execution class
 */
class ScreenshotMigration
{
    private $s3Client;
    private $mode;
    private $stats = [
        'files_found' => 0,
        'files_copied' => 0,
        'files_verified' => 0,
        'files_deleted' => 0,
        'errors' => 0,
        'skipped' => 0
    ];

    public function __construct(string $mode)
    {
        $this->mode = $mode;
        $this->initializeS3Client();
    }

    private function initializeS3Client(): void
    {
        $this->log("Initializing S3 client...");

        if (!defined('AWS_ACCESS_KEY') || !defined('AWS_SECRET_KEY')) {
            $this->error("AWS credentials not found in settings.inc.php");
            exit(1);
        }

        try {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => AWS_REGION,
                'credentials' => [
                    'key' => AWS_ACCESS_KEY,
                    'secret' => AWS_SECRET_KEY,
                ],
            ]);
            $this->log("S3 client initialized successfully");
        } catch (Exception $e) {
            $this->error("Failed to initialize S3 client: " . $e->getMessage());
            exit(1);
        }
    }

    public function run(): void
    {
        $this->log("=== Starting Screenshot Migration ===");
        $this->log("Mode: " . $this->mode);
        $this->log("Bucket: " . S3_BUCKET);
        $this->log("Time: " . date('Y-m-d H:i:s'));
        $this->log("");

        // Get list of videos from database
        $videos = $this->getVideosFromDatabase();
        $this->log("Found " . count($videos) . " videos with capture directories");
        $this->log("");

        // Process each video
        foreach ($videos as $video) {
            $this->processVideo($video);
        }

        // Print summary
        $this->printSummary();
    }

    private function getVideosFromDatabase(): array
    {
        $this->log("Querying database for videos with capture directories...");

        $database = new Database();
        $database->connect_mysqli();

        $sql = "SELECT id, chamber, date, capture_directory
                FROM files
                WHERE capture_directory IS NOT NULL
                AND capture_directory != ''
                ORDER BY date DESC, chamber ASC";

        $result = mysqli_query($GLOBALS['db'], $sql);

        if (!$result) {
            $this->error("Database query failed: " . mysqli_error($GLOBALS['db']));
            exit(1);
        }

        $videos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $videos[] = $row;
        }

        return $videos;
    }

    private function processVideo(array $video): void
    {
        $this->log("Processing video ID {$video['id']}: {$video['chamber']} {$video['date']}");
        $this->log("  capture_directory: {$video['capture_directory']}");

        // Build S3 prefix for this video's screenshots
        // capture_directory might be like "/video/senate/20200221/screenshots/"
        // We need to get to "senate/20200221/screenshots/" for S3 prefix
        $captureDir = trim($video['capture_directory'], '/');
        $captureDir = preg_replace('#^video/#', '', $captureDir);

        // If capture_directory already contains "screenshots", look there
        // Otherwise, append "screenshots" subdirectory
        if (strpos($captureDir, 'screenshots') !== false) {
            // Already has screenshots in the path, just add full/thumbnail
            $prefixes = [
                $captureDir . '/full/',
                $captureDir . '/thumbnail/'
            ];
        } else {
            // Need to add screenshots subdirectory
            $prefixes = [
                $captureDir . '/screenshots/full/',
                $captureDir . '/screenshots/thumbnail/'
            ];
        }

        foreach ($prefixes as $prefix) {
            $this->log("  Searching S3 prefix: {$prefix}");
            $this->processPrefix($prefix, $video);
        }
    }

    private function processPrefix(string $prefix, array $video): void
    {
        try {
            // List objects with this prefix
            $results = $this->s3Client->getPaginator('ListObjects', [
                'Bucket' => S3_BUCKET,
                'Prefix' => $prefix
            ]);

            $filesInPrefix = 0;
            foreach ($results as $result) {
                if (!isset($result['Contents'])) {
                    continue;
                }

                foreach ($result['Contents'] as $object) {
                    $this->stats['files_found']++;
                    $filesInPrefix++;
                    $this->processObject($object['Key'], $prefix);
                }
            }

            if ($filesInPrefix > 0) {
                $this->log("    Found {$filesInPrefix} files");
            }
        } catch (AwsException $e) {
            $this->error("Failed to list objects with prefix '{$prefix}': " . $e->getMessage());
            $this->stats['errors']++;
        }
    }

    private function processObject(string $oldKey, string $prefix): void
    {
        // Parse the old key
        // Example: senate/20200221/screenshots/full/02796.jpg
        $filename = basename($oldKey);
        $pathParts = explode('/', $oldKey);

        // Determine if this is a thumbnail or full image
        $isThumbnail = (strpos($oldKey, '/screenshots/thumbnail/') !== false);

        // Extract the base directory (chamber/date)
        $baseDir = '';
        foreach ($pathParts as $i => $part) {
            if ($part === 'screenshots') {
                break;
            }
            if (!empty($baseDir)) {
                $baseDir .= '/';
            }
            $baseDir .= $part;
        }

        // Parse filename - should be something like "02796.jpg"
        if (!preg_match('/^(\d+)\.jpg$/i', $filename, $matches)) {
            $this->log("  Skipping non-matching filename: {$filename}");
            $this->stats['skipped']++;
            return;
        }

        $frameNumber = $matches[1];

        // Pad to 8 characters
        $paddedNumber = str_pad($frameNumber, 8, '0', STR_PAD_LEFT);

        // Build new key
        if ($isThumbnail) {
            $newKey = $baseDir . '/' . $paddedNumber . '-thumbnail.jpg';
        } else {
            $newKey = $baseDir . '/' . $paddedNumber . '.jpg';
        }

        // Execute based on mode
        switch ($this->mode) {
            case 'dry-run':
                $this->log("  [DRY-RUN] Would copy: {$oldKey} -> {$newKey}");
                $this->stats['files_copied']++;
                break;

            case 'execute':
                $this->copyFile($oldKey, $newKey);
                break;

            case 'verify-only':
                $this->verifyFile($oldKey, $newKey);
                break;

            case 'cleanup':
                $this->deleteOldFile($oldKey, $newKey);
                break;
        }
    }

    private function copyFile(string $oldKey, string $newKey): void
    {
        // Check if destination already exists
        try {
            $this->s3Client->headObject([
                'Bucket' => S3_BUCKET,
                'Key' => $newKey
            ]);
            $this->log("  Skipping (already exists): {$newKey}");
            $this->stats['skipped']++;
            return;
        } catch (AwsException $e) {
            // File doesn't exist, proceed with copy
        }

        // Copy with retries
        $attempt = 0;
        while ($attempt < MAX_RETRIES) {
            $attempt++;

            try {
                $this->s3Client->copyObject([
                    'Bucket' => S3_BUCKET,
                    'CopySource' => S3_BUCKET . '/' . $oldKey,
                    'Key' => $newKey,
                    'ACL' => 'public-read',
                    'MetadataDirective' => 'COPY'
                ]);

                $this->log("  Copied: {$oldKey} -> {$newKey}");
                $this->stats['files_copied']++;
                return;
            } catch (AwsException $e) {
                if ($attempt >= MAX_RETRIES) {
                    $this->error("  Failed to copy after {$attempt} attempts: {$oldKey}");
                    $this->error("    Error: " . $e->getMessage());
                    $this->stats['errors']++;
                    return;
                }
                $this->log("  Retry {$attempt}/{" . MAX_RETRIES . "} for: {$oldKey}");
                sleep(2);
            }
        }
    }

    private function verifyFile(string $oldKey, string $newKey): void
    {
        try {
            // Check if old file exists
            $oldObject = $this->s3Client->headObject([
                'Bucket' => S3_BUCKET,
                'Key' => $oldKey
            ]);

            // Check if new file exists
            $newObject = $this->s3Client->headObject([
                'Bucket' => S3_BUCKET,
                'Key' => $newKey
            ]);

            // Compare sizes
            $oldSize = $oldObject['ContentLength'];
            $newSize = $newObject['ContentLength'];

            if ($oldSize === $newSize) {
                $this->stats['files_verified']++;
            } else {
                $this->error("  Size mismatch: {$oldKey} ({$oldSize} bytes) vs {$newKey} ({$newSize} bytes)");
                $this->stats['errors']++;
            }
        } catch (AwsException $e) {
            $this->error("  Verification failed for: {$oldKey} -> {$newKey}");
            $this->error("    Error: " . $e->getMessage());
            $this->stats['errors']++;
        }
    }

    private function deleteOldFile(string $oldKey, string $newKey): void
    {
        // First verify the new file exists
        try {
            $this->s3Client->headObject([
                'Bucket' => S3_BUCKET,
                'Key' => $newKey
            ]);
        } catch (AwsException $e) {
            $this->error("  Cannot delete {$oldKey}: new file {$newKey} doesn't exist");
            $this->stats['errors']++;
            return;
        }

        // Now delete the old file
        try {
            $this->s3Client->deleteObject([
                'Bucket' => S3_BUCKET,
                'Key' => $oldKey
            ]);

            $this->log("  Deleted: {$oldKey}");
            $this->stats['files_deleted']++;
        } catch (AwsException $e) {
            $this->error("  Failed to delete: {$oldKey}");
            $this->error("    Error: " . $e->getMessage());
            $this->stats['errors']++;
        }
    }

    private function printSummary(): void
    {
        $this->log("");
        $this->log("=== Migration Summary ===");
        $this->log("Files found:    " . $this->stats['files_found']);

        switch ($this->mode) {
            case 'dry-run':
            case 'execute':
                $this->log("Files copied:   " . $this->stats['files_copied']);
                break;
            case 'verify-only':
                $this->log("Files verified: " . $this->stats['files_verified']);
                break;
            case 'cleanup':
                $this->log("Files deleted:  " . $this->stats['files_deleted']);
                break;
        }

        $this->log("Files skipped:  " . $this->stats['skipped']);
        $this->log("Errors:         " . $this->stats['errors']);
        $this->log("");
        $this->log("Completed at: " . date('Y-m-d H:i:s'));
    }

    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] {$message}\n";

        echo $message . "\n";
        file_put_contents(LOG_FILE, $line, FILE_APPEND);
    }

    private function error(string $message): void
    {
        $this->log("ERROR: " . $message);
    }
}

// Parse command line arguments
if ($argc < 2) {
    echo "Usage: php migrate-video-screenshots.php [--dry-run|--execute|--verify-only|--cleanup]\n\n";
    echo "Modes:\n";
    echo "  --dry-run     Test migration without making changes\n";
    echo "  --execute     Perform migration (copy files to new locations)\n";
    echo "  --verify-only Verify that copied files match originals\n";
    echo "  --cleanup     Delete old files (ONLY after verification)\n";
    echo "\n";
    exit(1);
}

$mode = ltrim($argv[1], '-');

if (!in_array($mode, ['dry-run', 'execute', 'verify-only', 'cleanup'])) {
    echo "Error: Invalid mode '{$mode}'\n";
    exit(1);
}

// Confirmation for execute and cleanup modes
if (in_array($mode, ['execute', 'cleanup'])) {
    echo "WARNING: You are about to run in {$mode} mode.\n";
    if ($mode === 'cleanup') {
        echo "This will DELETE files from S3. Make sure you've verified the migration first!\n";
    }
    echo "Type 'yes' to continue: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if ($line !== 'yes') {
        echo "Aborted.\n";
        exit(0);
    }
}

// Run migration
$migration = new ScreenshotMigration($mode);
$migration->run();

