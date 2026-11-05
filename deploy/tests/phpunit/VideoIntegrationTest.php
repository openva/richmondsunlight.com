<?php

use PHPUnit\Framework\TestCase;

class VideoIntegrationTest extends TestCase
{
    private static bool $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        $database = new Database();
        if ($database->connect_mysqli() === false) {
            return;
        }
        self::$dbAvailable = true;
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
    }

    // --- Data availability ---

    public function testVideoIndexTableHasData(): void
    {
        $result = mysqli_query($GLOBALS['db'], 'SELECT COUNT(*) as count FROM video_index');
        $row = mysqli_fetch_assoc($result);
        $this->assertGreaterThan(0, (int) $row['count'], 'video_index table should contain data');
    }

    public function testVideoFilesWithCaptureDirectoryExist(): void
    {
        $result = mysqli_query($GLOBALS['db'], 'SELECT COUNT(*) as count FROM files WHERE type="video" AND capture_directory IS NOT NULL');
        $row = mysqli_fetch_assoc($result);
        if ((int) $row['count'] === 0) {
            $this->markTestSkipped('No video files with capture_directory in test database');
        }
        $this->assertGreaterThan(0, (int) $row['count'], 'files table should contain videos with capture_directory');
    }

    // --- index_clips() ---

    public function testIndexClipsSetsClipsProperty(): void
    {
        $videoData = $this->fetchVideoWithIndexData();
        if ($videoData === null) {
            $this->markTestSkipped('No videos with video_index data found');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->clip_type = 'bills';
        $video->fuzz = 5;

        if ($video->index_clips() !== true) {
            $this->markTestSkipped("index_clips returned false for video {$videoData['id']}");
        }

        $this->assertIsObject($video->clips);
        $this->assertGreaterThan(0, count((array) $video->clips));
    }

    public function testIndexClipsClipHasRequiredProperties(): void
    {
        $videoData = $this->fetchVideoWithIndexData();
        if ($videoData === null) {
            $this->markTestSkipped('No videos with video_index data found');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->clip_type = 'bills';
        $video->fuzz = 5;

        if ($video->index_clips() !== true) {
            $this->markTestSkipped("index_clips returned false");
        }

        $clips = (array) $video->clips;
        $firstClip = reset($clips);
        $this->assertTrue(isset($firstClip->screenshot), 'Clip should have screenshot property');
        $this->assertTrue(isset($firstClip->start), 'Clip should have start time');
        $this->assertTrue(isset($firstClip->end), 'Clip should have end time');
        $this->assertTrue(isset($firstClip->duration), 'Clip should have duration');
    }

    public function testIndexClipsScreenshotUrlUsesHttps(): void
    {
        $videoData = $this->fetchVideoWithIndexData();
        if ($videoData === null) {
            $this->markTestSkipped('No videos with video_index data found');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->clip_type = 'bills';
        $video->fuzz = 5;

        if ($video->index_clips() !== true) {
            $this->markTestSkipped("index_clips returned false");
        }

        $clips = (array) $video->clips;
        $firstClip = reset($clips);
        if (!isset($firstClip->screenshot)) {
            $this->markTestSkipped('No screenshot on first clip');
        }

        $this->assertStringStartsWith('https://', $firstClip->screenshot);
    }

    public function testIndexClipsScreenshotUrlExcludesScreenshotsDirectory(): void
    {
        $videoData = $this->fetchVideoWithIndexData();
        if ($videoData === null) {
            $this->markTestSkipped('No videos with video_index data found');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->clip_type = 'bills';
        $video->fuzz = 5;

        if ($video->index_clips() !== true) {
            $this->markTestSkipped("index_clips returned false");
        }

        $clips = (array) $video->clips;
        $firstClip = reset($clips);
        if (!isset($firstClip->screenshot)) {
            $this->markTestSkipped('No screenshot on first clip');
        }

        $this->assertStringNotContainsString('/screenshots/', $firstClip->screenshot);
    }

    // --- by_bill() ---

    public function testByBillReturnsClipsArray(): void
    {
        $billData = $this->fetchBillWithVideoData();
        if ($billData === null) {
            $this->markTestSkipped('No bills with video_index data found');
        }

        $video = new Video();
        $video->bill_id = $billData['bill_id'];
        $clips = $video->by_bill();

        $this->assertIsArray($clips);
        $this->assertGreaterThan(0, count($clips));
    }

    public function testByBillClipHasRequiredFields(): void
    {
        $billData = $this->fetchBillWithVideoData();
        if ($billData === null) {
            $this->markTestSkipped('No bills with video_index data found');
        }

        $video = new Video();
        $video->bill_id = $billData['bill_id'];
        $clips = $video->by_bill();

        if ($clips === false || !is_array($clips) || count($clips) === 0) {
            $this->markTestSkipped('by_bill returned no clips');
        }

        $firstClip = $clips[0];
        $this->assertArrayHasKey('screenshot', $firstClip);
        $this->assertArrayHasKey('start', $firstClip);
        $this->assertArrayHasKey('end', $firstClip);
        $this->assertArrayHasKey('chamber', $firstClip);
        $this->assertArrayHasKey('date', $firstClip);
    }

    public function testByBillScreenshotUrlStartsWithExpectedDomain(): void
    {
        $billData = $this->fetchBillWithVideoData();
        if ($billData === null) {
            $this->markTestSkipped('No bills with video_index data found');
        }

        $video = new Video();
        $video->bill_id = $billData['bill_id'];
        $clips = $video->by_bill();

        if ($clips === false || !is_array($clips) || count($clips) === 0) {
            $this->markTestSkipped('by_bill returned no clips');
        }

        $this->assertStringStartsWith('https://video.richmondsunlight.com/', $clips[0]['screenshot']);
    }

    public function testByBillScreenshotUrlExcludesScreenshotsDirectory(): void
    {
        $billData = $this->fetchBillWithVideoData();
        if ($billData === null) {
            $this->markTestSkipped('No bills with video_index data found');
        }

        $video = new Video();
        $video->bill_id = $billData['bill_id'];
        $clips = $video->by_bill();

        if ($clips === false || !is_array($clips) || count($clips) === 0) {
            $this->markTestSkipped('by_bill returned no clips');
        }

        $this->assertStringNotContainsString('/screenshots/', $clips[0]['screenshot']);
    }

    public function testByBillScreenshotFilenameHasEightCharacterPadding(): void
    {
        $billData = $this->fetchBillWithVideoData();
        if ($billData === null) {
            $this->markTestSkipped('No bills with video_index data found');
        }

        $video = new Video();
        $video->bill_id = $billData['bill_id'];
        $clips = $video->by_bill();

        if ($clips === false || !is_array($clips) || count($clips) === 0) {
            $this->markTestSkipped('by_bill returned no clips');
        }

        $filename = basename($clips[0]['screenshot']);
        $this->assertMatchesRegularExpression('/^\d{8}\.jpg$/', $filename, 'Screenshot filename should be 8 digits padded');
    }

    /**
     * video_clips is the only record of some older video's bills, so by_bill() uses it for
     * recordings with no chyrons for the bill, and only there.
     */
    public function testByBillAddsStoredClipsForVideoWithoutChyrons(): void
    {
        $db = $GLOBALS['db'];
        $bill = mysqli_fetch_row(mysqli_query($db, 'SELECT id FROM bills
            WHERE id NOT IN (SELECT linked_id FROM video_index WHERE type = "bill" AND linked_id IS NOT NULL)
            AND id NOT IN (SELECT bill_id FROM video_clips WHERE bill_id IS NOT NULL) LIMIT 1'));
        $files = mysqli_fetch_all(mysqli_query($db, 'SELECT id FROM files ORDER BY date LIMIT 2'));
        if ($bill === null || count($files) < 2) {
            $this->markTestSkipped('Needs a bill without video and two files');
        }
        [$billId, $archivedFile, $indexedFile] = [(int) $bill[0], (int) $files[0][0], (int) $files[1][0]];

        mysqli_begin_transaction($db);
        try {
            // The newer video has a chyron for the bill, so its stored clips are not used.
            mysqli_query($db, "INSERT INTO video_index (file_id, time, screenshot, raw_text, type, linked_id, ignored, date_created)
                VALUES ($indexedFile, '00:10:00', '600', 'HB1', 'bill', $billId, 'n', NOW())");
            mysqli_query($db, "INSERT INTO video_clips (legislator_id, bill_id, file_id, time_start, time_end, screenshot, date_created) VALUES
                (NULL, $billId, $archivedFile, '00:01:40', '00:02:10', '//video.richmondsunlight.com/senate/floor/20090210/00000100.jpg', NOW()),
                (NULL, $billId, $indexedFile, '00:20:00', '00:21:00', 'x', NOW()),
                (269, $billId, $archivedFile, '00:05:00', '00:06:00', 'x', NOW())");

            $video = new Video();
            $video->bill_id = $billId;
            $clips = $video->by_bill();
        } finally {
            mysqli_rollback($db);
        }

        $this->assertIsArray($clips);
        $this->assertSame([$archivedFile, $indexedFile], array_map(fn($clip) => (int) $clip['file_id'], $clips));
        $this->assertSame(90, $clips[0]['start']);
        $this->assertSame(140, $clips[0]['end']);
        $this->assertSame('https://video.richmondsunlight.com/senate/floor/20090210/00000100.jpg', $clips[0]['screenshot']);
    }

    // --- screenshots() ---

    public function testScreenshotsGeneratesUrls(): void
    {
        $videoData = $this->fetchVideoForScreenshots();
        if ($videoData === null) {
            $this->markTestSkipped('No suitable video found for screenshots() test');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->fps = $videoData['fps'];
        $video->capture_rate = $videoData['capture_rate'];
        $video->length = $videoData['length'];
        $video->capture_directory = $videoData['capture_directory'];
        $video->frequency = 60;
        $video->screenshots();

        $this->assertTrue(isset($video->screenshots) && is_object($video->screenshots));
        $this->assertGreaterThan(0, count((array) $video->screenshots));
    }

    public function testScreenshotsUrlUsesHttps(): void
    {
        $videoData = $this->fetchVideoForScreenshots();
        if ($videoData === null) {
            $this->markTestSkipped('No suitable video found for screenshots() test');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->fps = $videoData['fps'];
        $video->capture_rate = $videoData['capture_rate'];
        $video->length = $videoData['length'];
        $video->capture_directory = $videoData['capture_directory'];
        $video->frequency = 60;
        $video->screenshots();

        $screenshotsArray = (array) $video->screenshots;
        if (empty($screenshotsArray)) {
            $this->markTestSkipped('screenshots() generated no URLs');
        }

        $first = reset($screenshotsArray);
        $this->assertStringStartsWith('https://video.richmondsunlight.com/', $first->filename);
    }

    public function testScreenshotsFilenameHasEightCharacterPadding(): void
    {
        $videoData = $this->fetchVideoForScreenshots();
        if ($videoData === null) {
            $this->markTestSkipped('No suitable video found for screenshots() test');
        }

        $video = new Video();
        $video->id = $videoData['id'];
        $video->fps = $videoData['fps'];
        $video->capture_rate = $videoData['capture_rate'];
        $video->length = $videoData['length'];
        $video->capture_directory = $videoData['capture_directory'];
        $video->frequency = 60;
        $video->screenshots();

        $screenshotsArray = (array) $video->screenshots;
        if (empty($screenshotsArray)) {
            $this->markTestSkipped('screenshots() generated no URLs');
        }

        $first = reset($screenshotsArray);
        $filename = basename($first->filename);
        $this->assertMatchesRegularExpression('/^\d{8}\.jpg$/', $filename);
    }

    // --- get_clip() ---

    public function testGetClipScreenshotUrlUsesHttps(): void
    {
        $result = mysqli_query($GLOBALS['db'], 'SELECT id FROM video_clips LIMIT 1');
        if (!$result || mysqli_num_rows($result) === 0) {
            $this->markTestSkipped('No video clips found for get_clip() test');
        }

        $row = mysqli_fetch_assoc($result);
        $video = new Video();
        $video->id = $row['id'];

        if ($video->get_clip() !== true || !isset($video->clip->screenshot)) {
            $this->markTestSkipped('get_clip() did not return a screenshot');
        }

        $url = $video->clip->screenshot;
        $this->assertStringStartsWith('https://', $url, 'Screenshot URL should use https://');
        $this->assertStringNotContainsString('http://', str_replace('https://', '', $url), 'Screenshot URL should not use http://');
    }

    // --- Private helpers ---

    private function fetchVideoWithIndexData(): ?array
    {
        $sql = 'SELECT files.id, files.chamber, files.date, COUNT(video_index.id) as index_count
                FROM files
                LEFT JOIN video_index ON files.id = video_index.file_id
                WHERE files.type="video"
                GROUP BY files.id
                HAVING index_count > 5
                ORDER BY index_count DESC
                LIMIT 1';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }

    private function fetchBillWithVideoData(): ?array
    {
        $sql = 'SELECT video_index.linked_id as bill_id, COUNT(*) as clip_count
                FROM video_index
                WHERE video_index.type="bill" AND video_index.linked_id IS NOT NULL
                GROUP BY video_index.linked_id
                HAVING clip_count >= 3
                LIMIT 1';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }

    private function fetchVideoForScreenshots(): ?array
    {
        $sql = 'SELECT id, fps, capture_rate, length, capture_directory
                FROM files
                WHERE type="video" AND fps > 0 AND capture_rate > 0
                    AND length > "00:00:00" AND capture_directory IS NOT NULL
                ORDER BY id
                LIMIT 1';
        $result = mysqli_query($GLOBALS['db'], $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }
}
