<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Every legislator's video clips, and every video's captions, in the local database.
 */
#[Group('local')]
class VideoSweepTest extends LocalTestCase
{
    public function testEveryLegislatorsClipsArePlayable(): void
    {
        $people = self::column('SELECT linked_id FROM video_index
                WHERE type = "legislator" AND linked_id IS NOT NULL
            UNION
            SELECT legislator_id FROM video_clips
                WHERE legislator_id IS NOT NULL');

        $problems = [];
        foreach ($people as $person_id) {
            $clips = Video::person_clips((int) $person_id);
            $previous = null;
            foreach ($clips as $clip) {
                $label = 'Person ' . $person_id . '’s clip at ' . $clip['start'] . 's of file ' . $clip['file_id'];
                if ($clip['start'] < 0 || $clip['end'] <= $clip['start']) {
                    $problems[] = $label . ' runs from ' . $clip['start'] . 's to ' . $clip['end'] . 's';
                }
                if (!str_starts_with($clip['path'], 'https://')) {
                    $problems[] = $label . ' isn’t at an HTTPS URL: ' . $clip['path'];
                }
                if ($clip['bill_id'] !== null && $clip['bill'] === null) {
                    $problems[] = $label . ' is about bill ' . $clip['bill_id'] . ', which doesn’t exist';
                }
                $order = [$clip['date'], $clip['file_id'], $clip['start']];
                if ($previous !== null && $order < $previous) {
                    $problems[] = $label . ' is out of order';
                }
                $previous = $order;
            }
        }

        $this->assertNoProblems($problems, count($people), 'legislators’ clips');
    }

    public function testEveryVideosCaptionsAreValidWebVtt(): void
    {
        $file_ids = self::column('SELECT DISTINCT file_id FROM video_transcript');

        $problems = [];
        foreach ($file_ids as $file_id) {
            $vtt = Video::transcript_vtt(Video::transcript_span((int) $file_id, 0, 86399));
            $label = 'File ' . $file_id . '’s captions';

            $blocks = explode("\n\n", trim($vtt));
            if (array_shift($blocks) !== 'WEBVTT') {
                $problems[] = $label . ' don’t begin with a WEBVTT header';
                continue;
            }

            $previous_start = 0;
            foreach ($blocks as $i => $block) {
                $lines = explode("\n", $block);
                if (
                    count($lines) !== 2
                    || !preg_match('/^(\d{2}):(\d{2}):(\d{2})\.(\d{3}) --> (\d{2}):(\d{2}):(\d{2})\.(\d{3}) line:5%$/', $lines[0], $time)
                ) {
                    $problems[] = $label . ' have a malformed cue: ' . json_encode($block);
                    break;
                }
                $start = $time[1] * 3600 + $time[2] * 60 + $time[3] + $time[4] / 1000;
                $end = $time[5] * 3600 + $time[6] * 60 + $time[7] + $time[8] / 1000;
                if ($end <= $start || $start < $previous_start) {
                    $problems[] = $label . ' have a cue out of order: ' . $lines[0];
                    break;
                }
                if (trim($lines[1]) === '' || str_contains($lines[1], '<')) {
                    $problems[] = $label . ' have a cue with blank or unescaped text: ' . json_encode($block);
                    break;
                }
                $previous_start = $start;
            }
        }

        $this->assertNoProblems($problems, count($file_ids), 'videos’ captions');
    }
}
