-- Video Screenshot Database Migration
--
-- This script updates database paths to match the new S3 structure:
-- - Removes /screenshots/ from files.capture_directory
-- - Updates video_clips.screenshot paths to remove /screenshots/full/ and /screenshots/thumbnail/
--
-- IMPORTANT:
-- 1. Backup your database before running this!
-- 2. Run this AFTER the S3 migration is complete and verified
-- 3. Test on staging database first
--
-- Usage:
--   mysql -u username -p database_name < migrate-screenshot-database.sql

-- Display current state before migration
SELECT '=== PRE-MIGRATION STATE ===' AS status;

SELECT 'files.capture_directory with /screenshots/:' AS check_type, COUNT(*) AS count
FROM files
WHERE capture_directory LIKE '%/screenshots/%';

SELECT 'video_clips.screenshot with /screenshots/:' AS check_type, COUNT(*) AS count
FROM video_clips
WHERE screenshot LIKE '%/screenshots/%';

-- Backup recommendation
SELECT '=== BACKUP RECOMMENDATION ===' AS status;
SELECT 'Please ensure you have a database backup before proceeding!' AS warning;
SELECT 'Press Ctrl+C now if you have not backed up your database.' AS warning;
SELECT 'Otherwise, continue with the migration.' AS warning;

-- Update files.capture_directory
-- Remove /screenshots/ path segment
SELECT '=== UPDATING files.capture_directory ===' AS status;

UPDATE files
SET capture_directory = REPLACE(capture_directory, '/screenshots/', '/')
WHERE capture_directory LIKE '%/screenshots/%';

SELECT 'Updated files.capture_directory records' AS action, ROW_COUNT() AS rows_affected;

-- Update video_clips.screenshot paths
-- Remove /screenshots/full/ (these become just the filename)
SELECT '=== UPDATING video_clips.screenshot (full images) ===' AS status;

UPDATE video_clips
SET screenshot = REPLACE(screenshot, '/screenshots/full/', '/')
WHERE screenshot LIKE '%/screenshots/full/%';

SELECT 'Updated video_clips.screenshot (full) records' AS action, ROW_COUNT() AS rows_affected;

-- Remove /screenshots/thumbnail/ (these become filename-thumbnail.jpg)
-- Note: This is a simple path removal. The -thumbnail suffix is already in the filename
-- if the rs-video-processor was configured correctly.
SELECT '=== UPDATING video_clips.screenshot (thumbnails) ===' AS status;

UPDATE video_clips
SET screenshot = REPLACE(screenshot, '/screenshots/thumbnail/', '/')
WHERE screenshot LIKE '%/screenshots/thumbnail/%';

SELECT 'Updated video_clips.screenshot (thumbnail) records' AS action, ROW_COUNT() AS rows_affected;

-- Verify results
SELECT '=== POST-MIGRATION VERIFICATION ===' AS status;

SELECT 'files.capture_directory still containing /screenshots/:' AS check_type, COUNT(*) AS count
FROM files
WHERE capture_directory LIKE '%/screenshots/%';

SELECT 'video_clips.screenshot still containing /screenshots/:' AS check_type, COUNT(*) AS count
FROM video_clips
WHERE screenshot LIKE '%/screenshots/%';

-- Show sample of updated records
SELECT '=== SAMPLE UPDATED RECORDS ===' AS status;

SELECT 'Sample files.capture_directory:' AS sample_type;
SELECT id, chamber, date, capture_directory
FROM files
WHERE capture_directory IS NOT NULL
AND capture_directory != ''
LIMIT 5;

SELECT 'Sample video_clips.screenshot:' AS sample_type;
SELECT id, file_id, screenshot
FROM video_clips
WHERE screenshot IS NOT NULL
AND screenshot != ''
LIMIT 5;

-- Summary
SELECT '=== MIGRATION COMPLETE ===' AS status;
SELECT 'If verification shows zero records with /screenshots/, migration was successful!' AS message;
SELECT 'If any records remain, investigate and re-run the specific UPDATE statements.' AS message;

-- Rollback instructions (for documentation)
SELECT '=== ROLLBACK INSTRUCTIONS (if needed) ===' AS status;
SELECT 'To rollback, restore from your database backup.' AS instructions;
SELECT 'Or manually run reverse REPLACE operations:' AS instructions;
SELECT 'UPDATE files SET capture_directory = REPLACE(capture_directory, \'/\', \'/screenshots/\') WHERE ...' AS example;
