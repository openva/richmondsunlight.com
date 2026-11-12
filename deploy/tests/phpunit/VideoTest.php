<?php

use PHPUnit\Framework\TestCase;

class VideoTest extends TestCase
{
    private static bool $dbAvailable = false;
    private static ?int $videoId = null;
    private static ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        self::$reflection = new ReflectionClass('Video');

        $database = new Database();
        if ($database->connect_mysqli() === false) {
            return;
        }
        self::$dbAvailable = true;

        $sql = 'SELECT id FROM files WHERE type="video" LIMIT 1';
        $result = @mysqli_query($GLOBALS['db'], $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_array($result);
            self::$videoId = (int) $row['id'];
        }
    }

    // --- Constants ---

    public function testConstantDefaultFuzzSeconds(): void
    {
        $this->assertSame(5, self::$reflection->getConstant('DEFAULT_FUZZ_SECONDS'));
    }

    public function testConstantMinFuzzForSameTime(): void
    {
        $this->assertSame(15, self::$reflection->getConstant('MIN_FUZZ_FOR_SAME_TIME'));
    }

    public function testConstantDefaultScreenshotFrequency(): void
    {
        $this->assertSame(60, self::$reflection->getConstant('DEFAULT_SCREENSHOT_FREQUENCY'));
    }

    public function testConstantClipPaddingSeconds(): void
    {
        $this->assertSame(10, self::$reflection->getConstant('CLIP_PADDING_SECONDS'));
    }

    public function testConstantClipBoundaryThreshold(): void
    {
        $this->assertSame(30, self::$reflection->getConstant('CLIP_BOUNDARY_THRESHOLD'));
    }

    // --- Method return types ---

    public function testGetVideoReturnsBool(): void
    {
        $method = self::$reflection->getMethod('get_video');
        $this->assertTrue($method->hasReturnType());
        $this->assertSame('bool', $method->getReturnType()->getName());
    }

    public function testSubmitReturnsBool(): void
    {
        $method = self::$reflection->getMethod('submit');
        $this->assertTrue($method->hasReturnType());
        $this->assertSame('bool', $method->getReturnType()->getName());
    }

    public function testIndexClipsReturnsBool(): void
    {
        $method = self::$reflection->getMethod('index_clips');
        $this->assertTrue($method->hasReturnType());
        $this->assertSame('bool', $method->getReturnType()->getName());
    }

    public function testGetClipsReturnsBool(): void
    {
        $method = self::$reflection->getMethod('get_clips');
        $this->assertTrue($method->hasReturnType());
        $this->assertSame('bool', $method->getReturnType()->getName());
    }

    public function testGenerateTranscriptReturnsBool(): void
    {
        $method = self::$reflection->getMethod('generate_transcript');
        $this->assertTrue($method->hasReturnType());
        $this->assertSame('bool', $method->getReturnType()->getName());
    }

    // --- Error handling: no ID/property set ---

    public function testGetVideoReturnsFalseWithNoId(): void
    {
        $video = new Video();
        $this->assertFalse($video->get_video());
    }

    public function testIndexClipsReturnsFalseWithNoId(): void
    {
        $video = new Video();
        $this->assertFalse($video->index_clips());
    }

    public function testGetClipsReturnsFalseWithNoId(): void
    {
        $video = new Video();
        $this->assertFalse($video->get_clips());
    }

    public function testGetClipsReturnsFalseWithNoClipType(): void
    {
        $video = new Video();
        $video->id = 1;
        $this->assertFalse($video->get_clips());
    }

    public function testGenerateTranscriptReturnsFalseWithNoFileId(): void
    {
        $video = new Video();
        $this->assertFalse($video->generate_transcript());
    }

    // --- Division by zero protection ---

    public function testScreenshotsReturnsFalseWhenBothRatesAreZero(): void
    {
        $video = new Video();
        $video->id = 1;
        $video->fps = 0;
        $video->capture_rate = 0;
        $this->assertFalse($video->screenshots());
    }

    public function testScreenshotsReturnsFalseWhenCaptureRateIsZero(): void
    {
        $video = new Video();
        $video->id = 1;
        $video->fps = 30;
        $video->capture_rate = 0;
        $this->assertFalse($video->screenshots());
    }

    // --- parse_sbv ---

    public function testParseSbvReturnsTrueWithValidData(): void
    {
        $video = new Video();
        $video->sbv = "0:00:00.000,0:00:05.000\nTest caption one\n-----\n0:00:05.000,0:00:10.000\nTest caption two";
        $this->assertTrue($video->parse_sbv());
    }

    public function testParseSbvRestoresSbvProperty(): void
    {
        $video = new Video();
        $video->sbv = "0:00:00.000,0:00:05.000\nTest caption one\n-----\n0:00:05.000,0:00:10.000\nTest caption two";
        $video->parse_sbv();
        $this->assertStringContainsString('Test caption', $video->sbv);
    }

    public function testParseSbvAcceptsBlankLineSeparator(): void
    {
        $video = new Video();
        $video->sbv = "0:00:00.000,0:00:05.000\nCaption one\n\n0:00:05.000,0:00:10.000\nCaption two";
        $this->assertTrue($video->parse_sbv());
        $this->assertStringContainsString('Caption one', $video->transcript);
        $this->assertStringContainsString('Caption two', $video->transcript);
    }

    // --- transcript_vtt() ---

    public function testTranscriptVttLeavesOutBlankLines(): void
    {
        $vtt = Video::transcript_vtt([[
            'start' => 144,
            'speaker' => 'Sen. Lucas',
            'lines' => [
                ['start' => 144, 'end' => 145, 'text' => ' '],
                ['start' => 145, 'end' => 146, 'text' => "The Senate\nis adjourned."],
                ['start' => 146, 'end' => 146, 'text' => ''],
            ],
        ]]);
        $this->assertSame(
            "WEBVTT\n\n00:02:25.000 --> 00:02:26.000 line:5%\nSen. Lucas: The Senate is adjourned.\n",
            $vtt
        );
    }

    // --- Method signatures ---

    public function testSubmitTakesNoParameters(): void
    {
        $method = self::$reflection->getMethod('submit');
        $this->assertSame(0, $method->getNumberOfParameters());
    }

    // --- DB-dependent ---

    public function testGetVideoReturnsTrueWithValidId(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
        if (self::$videoId === null) {
            $this->markTestSkipped('No video records in database');
        }
        $video = new Video();
        $video->id = self::$videoId;
        $this->assertTrue($video->get_video());
    }

    // --- Slugs, playable addresses, and sponsors (for video pages) ---

    public function testNewSlugsAreFourCharactersFromTheAlphabetWithALetter(): void
    {
        for ($i = 0; $i < 2000; $i++) {
            $slug = Video::new_slug();
            $this->assertMatchesRegularExpression('/^[bcdfghjkmnpqrstvwxz2-9]{4}$/', $slug);
            $this->assertFalse(ctype_digit($slug), "All digits: {$slug}");
            $this->assertTrue(Video::is_slug($slug));
        }
    }

    public function testIsSlugRejectsOtherThings(): void
    {
        $this->assertTrue(Video::is_slug('k7qm'));
        $this->assertFalse(Video::is_slug('2345'), 'all digits, like a video ID');
        $this->assertFalse(Video::is_slug('kaqm'), 'a vowel');
        $this->assertFalse(Video::is_slug('k1qm'), 'a 1');
        $this->assertFalse(Video::is_slug('K7QM'), 'uppercase');
        $this->assertFalse(Video::is_slug('k7q'), 'too short');
        $this->assertFalse(Video::is_slug('k7qmx'), 'too long');
    }

    public function testMediaUrlAcceptsOnlyHostsThatPagesMayPlay(): void
    {
        $this->assertSame(
            'https://video.richmondsunlight.com/senate/floor/20150212.mp4',
            Video::media_url('/video/senate/floor/20150212.mp4')
        );
        $this->assertSame(
            'https://archive.org/download/senate20150119/20150119.mp4',
            Video::media_url('http://archive.org/download/senate20150119/20150119.mp4')
        );
        $this->assertSame(
            'https://sg001-harmony01.sliq.net/00304archives/2026/01/26/a.mp4',
            Video::media_url('https://sg001-harmony01.sliq.net/00304archives/2026/01/26/a.mp4')
        );
        $this->assertNull(Video::media_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(Video::media_url('https://example.com/video.mp4'));
        $this->assertNull(Video::media_url('http://example.com/video.mp4'));
        $this->assertNull(Video::media_url(''));
        $this->assertNull(Video::media_url(null));
    }

    public function testYoutubeIdComesFromTheColumnOrAYoutubeUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', Video::youtube_id('dQw4w9WgXcQ', null));
        $this->assertSame('dQw4w9WgXcQ', Video::youtube_id(null, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', Video::youtube_id(null, 'https://youtu.be/dQw4w9WgXcQ'));
        $this->assertNull(Video::youtube_id(null, 'https://archive.org/download/x/y.mp4'));
        $this->assertNull(Video::youtube_id('not an id', null));
    }

    public function testSponsorHtmlKeepsOnlyLinksImagesAndText(): void
    {
        $this->assertSame('', Video::sponsor_html(null));
        $this->assertSame('Smith &amp; Jones', Video::sponsor_html('Smith & Jones'));
        $this->assertSame(
            '<a href="https://example.com/"><img src="https://example.com/logo.png" alt="Example"></a>',
            Video::sponsor_html('<a href="https://example.com/" onclick="evil()"><img src="https://example.com/logo.png" alt="Example" onerror="evil()"></a>')
        );
        $this->assertSame('Example', Video::sponsor_html('<a href="javascript:evil()">Example</a>'));
        $this->assertSame('evil()', Video::sponsor_html('<script>evil()</script>'));
    }

    public function testPagePathLinksToTheSlugAndATime(): void
    {
        $this->assertSame('/video/k7qm/', Video::page_path('k7qm'));
        $this->assertSame('/video/k7qm/?t=125', Video::page_path('k7qm', 125.6));
        $this->assertSame('/video/k7qm/', Video::page_path('k7qm', 0));
    }
}
