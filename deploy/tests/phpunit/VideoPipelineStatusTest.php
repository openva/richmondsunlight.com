<?php

use PHPUnit\Framework\TestCase;

class VideoPipelineStatusTest extends TestCase
{
    /** 2026-03-13 12:00:00 UTC */
    private const NOW = 1773403200;

    /**
     * A 2026 video that has been downloaded to S3 and nothing else.
     */
    private function video(array $overrides = []): array
    {
        return array_merge([
            'id' => 18867,
            'date' => '2026-03-13',
            'capture_directory' => null,
            'on_s3' => true,
            'archived' => false,
            'embedded' => false,
            'youtube' => false,
            'has_source' => true,
            'screenshots' => false,
            'transcript' => false,
            'bills' => false,
            'speakers' => false,
            'has_captions' => false,
            'has_transcript_text' => false,
        ], $overrides);
    }

    private function state(string $stage, array $video, ?array $job = null): string
    {
        return VideoPipelineStatus::stageState($stage, $video, $job, self::NOW)['state'];
    }

    public function testDoneStagesAreDone(): void
    {
        $video = $this->video(['screenshots' => true, 'transcript' => true]);
        $this->assertSame('done', $this->state('download', $video));
        $this->assertSame('done', $this->state('screenshots', $video));
        $this->assertSame('done', $this->state('transcript', $video));
    }

    public function testAStageWhoseInputsExistIsWaiting(): void
    {
        $this->assertSame('waiting', $this->state('screenshots', $this->video()));
        $this->assertSame('waiting', $this->state('transcript', $this->video()));
    }

    public function testAStageWaitingOnAnEarlierOneIsBlocked(): void
    {
        $this->assertSame('blocked', $this->state('bills', $this->video()), 'Bills need screenshots.');
        $this->assertSame('blocked', $this->state('archive', $this->video()));
        $this->assertSame('blocked', $this->state('screenshots', $this->video(['on_s3' => false])));
    }

    public function testBillsAndSpeakersDoNotApplyBefore2020(): void
    {
        $video = $this->video(['date' => '2019-02-01', 'screenshots' => true]);
        $this->assertSame('n/a', $this->state('bills', $video));
        $this->assertSame('n/a', $this->state('speakers', $video));
    }

    public function testYouTubeDownloadsAreManual(): void
    {
        $this->assertSame('manual', $this->state('download', $this->video(['on_s3' => false, 'youtube' => true])));
    }

    public function testJobStatesShowWhileTheStageIsNotDone(): void
    {
        $video = $this->video();
        $running = ['status' => 'running', 'attempts' => 1, 'claimed_at' => '2026-03-13 11:30:00', 'next_attempt_at' => null, 'last_error' => null];
        $retry = ['status' => 'retry', 'attempts' => 1, 'claimed_at' => null, 'next_attempt_at' => '2026-03-13 18:00:00', 'last_error' => 'MySQL server has gone away'];
        $failed = ['status' => 'failed', 'attempts' => 3, 'claimed_at' => null, 'next_attempt_at' => null, 'last_error' => 'Floating point exception'];

        $this->assertSame('running', $this->state('screenshots', $video, $running));
        $this->assertSame('retry', $this->state('screenshots', $video, $retry));
        $this->assertSame('failed', $this->state('screenshots', $video, $failed));
        $this->assertStringContainsString(
            'Floating point exception',
            VideoPipelineStatus::stageState('screenshots', $video, $failed, self::NOW)['detail']
        );
    }

    /**
     * The pipeline releases a claim older than three hours, and a retry whose
     * time has come is picked up on the next pass, so both are just waiting.
     */
    public function testStaleClaimsAndDueRetriesAreWaiting(): void
    {
        $video = $this->video();
        $stale = ['status' => 'running', 'attempts' => 1, 'claimed_at' => '2026-03-13 08:00:00', 'next_attempt_at' => null, 'last_error' => null];
        $due = ['status' => 'retry', 'attempts' => 1, 'claimed_at' => null, 'next_attempt_at' => '2026-03-13 11:00:00', 'last_error' => 'x'];

        $this->assertSame('waiting', $this->state('screenshots', $video, $stale));
        $this->assertSame('waiting', $this->state('screenshots', $video, $due));
    }

    public function testDoneWinsOverAnOldJobRecord(): void
    {
        $failed = ['status' => 'failed', 'attempts' => 3, 'claimed_at' => null, 'next_attempt_at' => null, 'last_error' => 'x'];
        $this->assertSame('done', $this->state('transcript', $this->video(['transcript' => true]), $failed));
    }

    public function testArchiveNeedsCaptionsScreenshotsAndATranscript(): void
    {
        $ready = $this->video(['screenshots' => true, 'has_captions' => true, 'has_transcript_text' => true]);
        $this->assertSame('waiting', $this->state('archive', $ready));
        $this->assertSame('done', $this->state('archive', $this->video(['on_s3' => false, 'archived' => true])));

        $noCaptions = VideoPipelineStatus::stageState('archive', $this->video(['screenshots' => true, 'has_transcript_text' => true]), null, self::NOW);
        $this->assertSame('blocked', $noCaptions['state']);
        $this->assertStringContainsString('captions', $noCaptions['detail']);
    }

    public function testVideosArchivedBefore2020WereNeverScreenshotted(): void
    {
        $video = $this->video(['date' => '2016-01-20', 'on_s3' => false, 'archived' => true]);
        $this->assertSame('n/a', $this->state('screenshots', $video));
        $this->assertSame('n/a', $this->state('transcript', $video));
    }

    public function testADeletedSourceCannotBeScreenshotted(): void
    {
        $this->assertSame('n/a', $this->state('screenshots', $this->video(['capture_directory' => '/deleted-source'])));
    }
}
