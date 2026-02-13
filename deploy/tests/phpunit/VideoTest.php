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
}
