<?php

use PHPUnit\Framework\TestCase;

/**
 * How a day's bills are scored, and its highlights chosen. The day is January 26, 2026, from
 * deploy/mysql/day-page-fixtures.sql, where HB 341 failed on a close vote, and has a news story, a
 * comment, page views, and poll votes.
 */
class DayHighlightsTest extends TestCase
{
    private const DATE = '2026-01-26';
    private const HB341 = 84141;

    protected function setUp(): void
    {
        $database = new Database();
        $database->connect_mysqli();
    }

    public function testScoresWhatWasDoneToABill(): void
    {
        $action = fn(string $type, ?string $outcome, string $text = '') => ['type' => $type, 'outcome' => $outcome, 'text' => $text];
        $this->assertSame(['floor_vote', 10.0], DayHighlights::action_points($action('failed', 'stopped')));
        $this->assertSame(['committee_stopped', 8.0], DayHighlights::action_points($action('committee', 'stopped')));
        $this->assertSame(['veto', 10.0], DayHighlights::action_points($action('governor', null, 'Vetoed by Governor')));
        $this->assertSame([null, 0.0], DayHighlights::action_points($action('reading', null)));
        $this->assertSame([null, 0.0], DayHighlights::action_points($action('referred', null)));
        $this->assertSame([null, 0.0], DayHighlights::action_points($action('conference', null, 'Conferees appointed by House')));
        $this->assertSame([null, 0.0], DayHighlights::action_points($action('conference', null, 'Conference Report released')));
        $this->assertSame(['conference', 3.0], DayHighlights::action_points($action('conference', null, 'Conference report agreed to by Senate')));
    }

    public function testKnowsACeremonialResolution(): void
    {
        $this->assertTrue(DayHighlights::is_ceremonial(['number' => 'hj311', 'catch_line' => 'Commending Virginia Youth Poet Laureate.']));
        $this->assertTrue(DayHighlights::is_ceremonial(['number' => 'sr5', 'catch_line' => 'Celebrating the life of Jane Doe.']));
        $this->assertFalse(DayHighlights::is_ceremonial(['number' => 'sj111', 'catch_line' => 'Booker T. Washington Commemorative Commission; reconstituted as a joint commission.']));
        $this->assertFalse(DayHighlights::is_ceremonial(['number' => 'hb5', 'catch_line' => 'Commending the use of seat belts.']));
    }

    public function testMeasuresHowCloseAVoteWas(): void
    {
        $vote = fn(int $yes, int $no) => ['yes' => $yes, 'no' => $no, 'abstaining' => 0, 'voice' => false];
        $this->assertSame(1.0, DayHighlights::closeness($vote(48, 48)));
        $this->assertSame(0.0, DayHighlights::closeness($vote(97, 0)));
        $this->assertEqualsWithDelta(0.928, DayHighlights::closeness($vote(45, 52)), 0.001);
        $this->assertSame(0.0, DayHighlights::closeness($vote(1, 1)));
        $this->assertSame(0.0, DayHighlights::closeness(['yes' => null, 'no' => null, 'abstaining' => null, 'voice' => true]));
        $this->assertSame(0.0, DayHighlights::closeness(null));
    }

    public function testSaysWhichChamberAnotherChambersCloseVoteWasIn(): void
    {
        $vote = ['yes' => 21, 'no' => 18, 'abstaining' => 0, 'voice' => false];
        $this->assertSame('Close vote, 21–18', DayHighlights::close_vote_label($vote, 'senate', 'senate'));
        // The highlight shows the House's vote, so the Senate's is named.
        $this->assertSame('Close Senate vote, 21–18', DayHighlights::close_vote_label($vote, 'senate', 'house'));
        $this->assertSame('Close vote, 21–18', DayHighlights::close_vote_label($vote, null, 'house'));
    }

    public function testMeasuresHowFarApartThePartiesVoted(): void
    {
        $this->assertSame(1.0, DayHighlights::party_line(['D' => ['Y' => 51], 'R' => ['N' => 49]]));
        // Both parties voted for it: no matter how lopsided, that's not partisan.
        $this->assertSame(0.0, DayHighlights::party_line(['D' => ['Y' => 51], 'R' => ['Y' => 49]]));
        $this->assertEqualsWithDelta(0.8, DayHighlights::party_line(['D' => ['Y' => 10], 'R' => ['Y' => 2, 'N' => 8]]), 0.001);
        // A party with fewer than two votes can't tell us anything.
        $this->assertNull(DayHighlights::party_line(['D' => ['Y' => 10], 'R' => ['N' => 1]]));
    }

    /**
     * Candidates, as select() takes them: score, chamber, and patron, by bill ID.
     */
    private static function candidates(array $rows): array
    {
        $candidates = [];
        foreach ($rows as $id => [$score, $chamber, $patron]) {
            $candidates[$id] = ['score' => $score, 'chamber' => $chamber, 'patron_id' => $patron, 'eligible' => true];
        }
        return $candidates;
    }

    public function testChoosesTheBestBillsUpToAPoint(): void
    {
        // Ten strong bills, and two weak ones. The weak ones don't make it.
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[$i] = [$i <= 10 ? 50 - $i : 5, $i % 2 ? 'house' : 'senate', $i];
        }
        $this->assertSame(range(1, 10), DayHighlights::select(self::candidates($rows)));

        // Past the minimum, a bill needs half the top score.
        $picked = DayHighlights::select(self::candidates([
            1 => [100, 'house', 1], 2 => [90, 'house', 2], 3 => [80, 'senate', 3], 4 => [70, 'senate', 4],
            5 => [20, 'house', 5], 6 => [45, 'house', 6], 7 => [10, 'senate', 7],
        ]));
        $this->assertSame([1, 2, 3, 4, 6], $picked);
    }

    public function testLimitsEachLegislatorToTwo(): void
    {
        $picked = DayHighlights::select(self::candidates([
            1 => [100, 'house', 7], 2 => [99, 'house', 7], 3 => [98, 'house', 7],
            4 => [60, 'senate', 8], 5 => [59, 'senate', 9],
        ]));
        $this->assertSame([1, 2, 4, 5], $picked);
    }

    public function testGivesEachChamberTwo(): void
    {
        // The Senate's bills score low, but it gets two of the ten.
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[$i] = [100 - $i, 'house', $i];
        }
        $rows[20] = [12, 'senate', 20];
        $rows[21] = [11, 'senate', 21];
        $rows[22] = [10, 'senate', 22];
        $picked = DayHighlights::select(self::candidates($rows));
        $this->assertCount(10, $picked);
        $this->assertSame([20, 21], array_values(array_intersect($picked, [20, 21, 22])));
        $this->assertNotContains(9, $picked);
        $this->assertNotContains(10, $picked);
    }

    public function testScoresADay(): void
    {
        $scored = DayHighlights::compute(self::DATE);
        $this->assertNotNull($scored);

        $hb341 = $scored[self::HB341];
        $this->assertSame(1, $hb341['position']);
        $this->assertSame('house', $hb341['chamber']);
        $this->assertSame('Read third time and defeated by House', $hb341['reasons']['action']['text']);
        $this->assertContains('Close vote, 45–52', $hb341['reasons']['labels']);
        $this->assertContains('1 news story', $hb341['reasons']['labels']);
        $this->assertContains('1 comment', $hb341['reasons']['labels']);
        $this->assertSame('b334', $hb341['reasons']['clip']['slug']);

        $signals = array_column($hb341['reasons']['signals'], 'points', 'signal');
        $this->assertSame(10.0, $signals['action']);
        $this->assertEqualsWithDelta(array_sum($signals), $hb341['score'], 0.02);

        // A bill that was only read a second time can't be a highlight.
        $this->assertFalse($scored[83690]['eligible']);
        $this->assertNull($scored[83690]['position']);

        // A bill that an admin hid isn't one.
        $hidden = DayHighlights::compute(self::DATE, [self::HB341]);
        $this->assertNull($hidden[self::HB341]['position']);

        $this->assertNull(DayHighlights::compute('2098-01-05'));
    }

    /**
     * The highlight reel plays the start of a clip, then another legislator, or the start alone.
     */
    public function testChoosesWhatTheHighlightReelPlays(): void
    {
        $clip = fn(int $duration, ?int $reply) => ['start' => 100, 'duration' => $duration, 'reply' => $reply];

        // The patron, then another legislator.
        $this->assertSame([['start' => 100, 'end' => 130], ['start' => 300, 'end' => 345]],
            DayHighlights::reel_segments($clip(600, 300)));
        // Nobody else spoke.
        $this->assertSame([['start' => 100, 'end' => 145]], DayHighlights::reel_segments($clip(600, null)));
        // Another legislator, soon after the start: one part.
        $this->assertSame([['start' => 100, 'end' => 178]], DayHighlights::reel_segments($clip(600, 133)));
        // A short clip plays whole.
        $this->assertSame([['start' => 100, 'end' => 125]], DayHighlights::reel_segments($clip(25, null)));
        $this->assertSame([['start' => 100, 'end' => 160]], DayHighlights::reel_segments($clip(60, 115)));
        // A clip stored before replies were, has none.
        $this->assertSame([['start' => 100, 'end' => 145]],
            DayHighlights::reel_segments(['start' => 100, 'duration' => 600]));
    }

    /**
     * A highlight, from the API, with a clip in a video that can be played here (or not).
     */
    private function highlight(string $number, ?string $mp4, ?string $captions = null, ?array $vote = null): array
    {
        return [
            'bill' => ['number' => $number, 'catch_line' => 'Title of ' . $number . ' & more <b>.'],
            'action' => ['text' => 'Passed', 'vote' => $vote],
            'clip' => [
                'mp4' => $mp4,
                'captions' => $captions,
                'segments' => [['start' => 100, 'end' => 130], ['start' => 200, 'end' => 245]],
            ],
        ];
    }

    public function testMakesTheHighlightReel(): void
    {
        $mp4 = 'https://archive.org/download/x/y.mp4';
        $reel = DayHighlights::reel([
            $this->highlight('hb1', $mp4, 'https://www.richmondsunlight.com/video/b334/captions.vtt',
                ['yes' => 45, 'no' => 52, 'voice' => false]),
            $this->highlight('sb20', $mp4, null, ['yes' => null, 'no' => null, 'voice' => true]),
            // Only on YouTube: left out.
            $this->highlight('hb3', null),
            // No clip: left out.
            ['bill' => ['number' => 'hb4', 'catch_line' => ''], 'action' => ['text' => ''], 'clip' => null],
        ]);
        $this->assertCount(2, $reel);
        $this->assertSame('HB 1', $reel[0]['number']);
        $this->assertSame('Passed, 45–52', $reel[0]['action']);
        $this->assertSame('b334', $reel[0]['captions']);
        $this->assertSame($mp4, $reel[0]['mp4']);
        $this->assertSame('SB 20', $reel[1]['number']);
        $this->assertSame('Passed', $reel[1]['action']);
        $this->assertNull($reel[1]['captions']);

        $html = DayHighlights::reel_html([$this->highlight('hb1', $mp4), $this->highlight('hb2', $mp4)]);
        $this->assertStringContainsString('(2 bills, 2:30)', $html);
        $this->assertStringContainsString('data-chapter="1">HB 2</button>', $html);
        // Its data is in a script, where nothing in it can end the script.
        $this->assertStringContainsString('<script>window.dayReel = [', $html);
        $this->assertStringNotContainsString('<b>', $html);

        // A reel needs two clips.
        $this->assertSame('', DayHighlights::reel_html([$this->highlight('hb1', $mp4), $this->highlight('hb3', null)]));
    }

    public function testStoresADaysHighlightsForTheApi(): void
    {
        $this->assertGreaterThan(0, DayHighlights::save(self::DATE));
        $day = DayHighlights::for_api(self::DATE);
        $this->assertSame('hb341', $day['highlights'][0]['bill']['number']);
        $this->assertStringStartsWith('https://www.richmondsunlight.com/video/b334/?t=', $day['highlights'][0]['clip']['url']);
        $this->assertNotEmpty($day['highlights'][0]['clip']['mp4']);
        $this->assertNotEmpty($day['highlights'][0]['clip']['segments']);
        $this->assertSame('https://www.richmondsunlight.com/video/b334/captions.vtt', $day['highlights'][0]['clip']['captions']);
        $this->assertArrayHasKey('reply', DayHighlights::compute(self::DATE)[self::HB341]['reasons']['clip']);
        $this->assertNotNull($day['updated']);

        // Hidden, it's gone, and stays gone when the day is scored again; shown, it's back.
        DayHighlights::set_hidden(self::DATE, self::HB341, true);
        DayHighlights::save(self::DATE);
        $this->assertNotContains('hb341', array_map(fn($h) => $h['bill']['number'], DayHighlights::for_api(self::DATE)['highlights']));
        DayHighlights::set_hidden(self::DATE, self::HB341, false);
        $this->assertSame('hb341', DayHighlights::for_api(self::DATE)['highlights'][0]['bill']['number']);
    }

    public function testFindsTheNewsAndWhatPeopleSaid(): void
    {
        $news = DayHighlights::news_for_api(self::DATE, [self::HB341]);
        $this->assertSame('House narrowly defeats plastic bag tax bill', $news[0]['title']);
        $this->assertSame('hb341', $news[0]['bills'][0]['number']);

        $activity = DayHighlights::site_activity_for_api(self::DATE, [self::HB341]);
        $this->assertSame('Day Page Fixture', $activity['comments'][0]['name']);
        $this->assertSame('https://www.richmondsunlight.com/bill/2026/hb341/#comments', $activity['comments'][0]['url']);
        $this->assertSame(['yes' => 7, 'no' => 3], array_intersect_key($activity['polls'][0], ['yes' => 0, 'no' => 0]));
        $this->assertSame(3, $activity['most_viewed'][0]['viewers']);
    }
}
