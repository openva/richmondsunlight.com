<?php

/**
 * Where each video stands in the rs-video-processor pipeline, for the admin
 * dashboard at /admin/video/pipeline.php. Read-only.
 *
 * A stage is "done" when its results exist, judged by the same rules as the
 * pipeline's queue classes (rs-video-processor/src/.../*JobQueue.php). If those
 * rules change there, change them here too. Whether a stage is running,
 * retrying or has been given up on comes from the video_jobs table, which the
 * pipeline writes. Its timestamps are UTC.
 */
class VideoPipelineStatus
{
    public const STAGES = [
        'download' => 'Download',
        'screenshots' => 'Screenshots',
        'transcript' => 'Transcript',
        'bills' => 'Bills',
        'speakers' => 'Speakers',
        'archive' => 'Archive',
    ];

    /** Every state stageState() returns, in the order the dashboard lists them. */
    public const STATES = [
        'done' => 'Done',
        'running' => 'Running',
        'waiting' => 'Waiting',
        'retry' => 'Retrying',
        'failed' => 'Failed',
        'blocked' => 'Blocked',
        'manual' => 'Manual',
        'n/a' => 'N/A',
    ];

    /** The pipeline releases a claim older than this (JobLedger::STALE_CLAIM_HOURS). */
    private const STALE_CLAIM_HOURS = 3;

    /** Bill and speaker detection only cover videos from this date on. */
    private const OCR_FROM = '2020-01-01';

    /**
     * One row per video, with a true/false flag for each thing the stage rules
     * look at. Large text columns are reduced to flags in SQL.
     *
     * @return array<int,array<string,mixed>> keyed by file ID, newest first
     */
    public static function loadVideos(mysqli $db, ?int $fileId = null): array
    {
        $sql = "SELECT f.id, f.chamber, f.title, f.date, f.capture_directory,
                    f.path LIKE 'https://video.richmondsunlight.com/%' AS on_s3,
                    f.path LIKE 'https://archive.org/%' AS archived,
                    (f.html IS NOT NULL AND f.html != '') AS embedded,
                    (f.video_index_cache LIKE '%youtube.com%' OR f.video_index_cache LIKE '%youtu.be%') AS youtube,
                    (f.video_index_cache LIKE '{%') AS has_source,
                    (f.capture_directory LIKE '/%/%' OR f.capture_directory LIKE 'https://%') AS screenshots,
                    EXISTS (SELECT 1 FROM video_transcript vt WHERE vt.file_id = f.id) AS transcript,
                    EXISTS (SELECT 1 FROM video_index vi WHERE vi.file_id = f.id AND vi.type = 'bill') AS bills,
                    EXISTS (SELECT 1 FROM video_index vi WHERE vi.file_id = f.id AND vi.type = 'legislator') AS speakers,
                    (f.webvtt IS NOT NULL OR f.srt IS NOT NULL) AS has_captions,
                    f.transcript IS NOT NULL AS has_transcript_text
                FROM files f
                WHERE f.type = 'video'" . ($fileId !== null ? ' AND f.id = ' . (int) $fileId : '') . '
                ORDER BY f.date DESC, f.id DESC';

        $videos = [];
        $result = mysqli_query($db, $sql);
        while ($result && $row = mysqli_fetch_assoc($result)) {
            foreach (['on_s3', 'archived', 'embedded', 'youtube', 'has_source', 'screenshots', 'transcript', 'bills', 'speakers', 'has_captions', 'has_transcript_text'] as $flag) {
                $row[$flag] = (bool) $row[$flag];
            }
            $videos[(int) $row['id']] = $row;
        }

        return $videos;
    }

    /**
     * Job records that are not "done", by file ID and stage.
     *
     * @return array<int,array<string,array<string,mixed>>>
     */
    public static function loadOpenJobs(mysqli $db): array
    {
        $jobs = [];
        $result = mysqli_query(
            $db,
            "SELECT file_id, stage, status, attempts, claimed_at, next_attempt_at, last_error, date_modified
             FROM video_jobs WHERE status != 'done'"
        );
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $jobs[(int) $row['file_id']][$row['stage']] = $row;
        }

        return $jobs;
    }

    /**
     * Jobs finished in the last $days days, for throughput.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function loadRecentlyDone(mysqli $db, int $days): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $rows = [];
        $result = mysqli_query(
            $db,
            "SELECT file_id, stage, claimed_at, date_modified FROM video_jobs
             WHERE status = 'done' AND date_modified >= '" . $since . "'"
        );
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The state of one stage for one video.
     *
     * @param array<string,mixed> $video a row from loadVideos()
     * @param array<string,mixed>|null $job its video_jobs row for this stage, if not done
     * @param int $now Unix time
     * @return array{state:string, detail:string}
     */
    public static function stageState(string $stage, array $video, ?array $job, int $now): array
    {
        $fixed = self::settledState($stage, $video);
        if ($fixed !== null) {
            return $fixed;
        }

        if ($job !== null) {
            $fromJob = self::jobState($job, $now);
            if ($fromJob !== null) {
                return $fromJob;
            }
        }

        $missing = self::missingInputs($stage, $video);
        if (!empty($missing)) {
            return ['state' => 'blocked', 'detail' => 'Needs ' . implode(', ', $missing) . ' first.'];
        }

        return ['state' => 'waiting', 'detail' => 'Ready; will run on the next pass.'];
    }

    /**
     * Done, not applicable, or done by hand: states no job record changes.
     */
    private static function settledState(string $stage, array $video): ?array
    {
        $beforeOcr = (string) $video['date'] < self::OCR_FROM;

        switch ($stage) {
            case 'download':
                if ($video['on_s3'] || $video['archived']) {
                    return ['state' => 'done', 'detail' => ''];
                }
                if ($video['embedded']) {
                    return ['state' => 'n/a', 'detail' => 'Embedded video; nothing to download.'];
                }
                if ($video['youtube']) {
                    return ['state' => 'manual', 'detail' => 'YouTube: downloaded with scripts/fetch_youtube_uploads.sh.'];
                }
                if (!$video['has_source']) {
                    return ['state' => 'n/a', 'detail' => 'No source URL recorded.'];
                }
                return null;

            case 'screenshots':
                if ($video['screenshots']) {
                    return ['state' => 'done', 'detail' => ''];
                }
                if ($video['capture_directory'] === '/deleted-source') {
                    return ['state' => 'n/a', 'detail' => 'The source video was deleted.'];
                }
                if ($video['archived'] && $beforeOcr) {
                    return ['state' => 'n/a', 'detail' => 'Archived before 2020; older videos are not screenshotted.'];
                }
                return null;

            case 'transcript':
                if ($video['transcript']) {
                    return ['state' => 'done', 'detail' => ''];
                }
                if ($video['archived']) {
                    return ['state' => 'n/a', 'detail' => 'Archived before it was transcribed; transcripts need the copy on S3.'];
                }
                return null;

            case 'bills':
            case 'speakers':
                if ($video[$stage]) {
                    return ['state' => 'done', 'detail' => ''];
                }
                if ($beforeOcr) {
                    return ['state' => 'n/a', 'detail' => 'Detection covers videos from 2020 on.'];
                }
                return null;

            case 'archive':
                if ($video['archived']) {
                    return ['state' => 'done', 'detail' => ''];
                }
                return null;
        }

        throw new InvalidArgumentException('Unknown pipeline stage: ' . $stage);
    }

    /**
     * Running, retrying or failed, from the job record; null when the record
     * no longer holds the job back (a stale claim, or a retry that is due).
     */
    private static function jobState(array $job, int $now): ?array
    {
        $attempts = (int) ($job['attempts'] ?? 0);
        $error = trim((string) ($job['last_error'] ?? ''));

        switch ($job['status'] ?? '') {
            case 'running':
                $claimed = self::utc($job['claimed_at'] ?? null);
                if ($claimed !== null && $claimed > $now - self::STALE_CLAIM_HOURS * 3600) {
                    return ['state' => 'running', 'detail' => 'Attempt ' . max(1, $attempts) . ', started ' . self::local($claimed) . '.'];
                }
                return null;

            case 'retry':
                $next = self::utc($job['next_attempt_at'] ?? null);
                if ($next !== null && $next > $now) {
                    return ['state' => 'retry', 'detail' => 'Attempt ' . $attempts . ' failed: ' . $error . ' Next try after ' . self::local($next) . '.'];
                }
                return null;

            case 'failed':
                return ['state' => 'failed', 'detail' => 'Gave up after ' . $attempts . ' attempt(s): ' . $error];
        }

        return null;
    }

    /**
     * @return string[] what the stage still needs from earlier stages
     */
    private static function missingInputs(string $stage, array $video): array
    {
        $missing = [];
        switch ($stage) {
            case 'screenshots':
                if (!$video['on_s3'] && !$video['archived']) {
                    $missing[] = 'download';
                }
                break;
            case 'transcript':
                if (!$video['on_s3']) {
                    $missing[] = 'download';
                }
                break;
            case 'bills':
                if (!$video['screenshots']) {
                    $missing[] = 'screenshots';
                }
                break;
            case 'speakers':
                if (!$video['on_s3'] && !$video['archived']) {
                    $missing[] = 'download';
                }
                if (!$video['screenshots']) {
                    $missing[] = 'screenshots';
                }
                break;
            case 'archive':
                if (!$video['on_s3']) {
                    $missing[] = 'download';
                }
                if (!$video['screenshots']) {
                    $missing[] = 'screenshots';
                }
                if (!$video['has_transcript_text']) {
                    $missing[] = 'transcript';
                }
                if (!$video['has_captions']) {
                    $missing[] = 'captions';
                }
                break;
        }

        return $missing;
    }

    /** Unix time from a UTC "Y-m-d H:i:s" string written by the pipeline. */
    public static function utc(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $time = DateTime::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $time === false ? null : $time->getTimestamp();
    }

    /** A Unix time in Richmond's time zone, for display. */
    public static function local(int $timestamp): string
    {
        return (new DateTime('@' . $timestamp))->setTimezone(new DateTimeZone('America/New_York'))->format('M j, g:i a');
    }
}
