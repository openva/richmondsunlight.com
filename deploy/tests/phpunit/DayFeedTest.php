<?php

use PHPUnit\Framework\TestCase;

/**
 * The RSS feed of days (/rss/days/), and the summary of a day that it shares with the day's page.
 * The day is January 26, 2026, from deploy/mysql/day-page-fixtures.sql.
 */
class DayFeedTest extends TestCase
{
    protected function setUp(): void
    {
        $database = new Database();
        $database->connect_mysqli();
    }

    public function testPublishesADayTheNextMorning(): void
    {
        // 7 a.m. in Richmond, in standard time and in daylight time.
        $this->assertSame('2026-01-27 07:00 -05:00', date('Y-m-d H:i P', DayFeed::published_at('2026-01-26')));
        $this->assertSame(strtotime('2026-04-23 11:00 UTC'), DayFeed::published_at('2026-04-22'));
    }

    public function testListsTheMostRecentPublishedDays(): void
    {
        $days = array_map(fn($date) => ['date' => $date], ['2026-01-20', '2026-01-26', '2026-01-22', '2026-01-27']);
        $before_seven = strtotime('2026-01-28 06:59 America/New_York');
        $this->assertSame(['2026-01-26', '2026-01-22', '2026-01-20'], DayFeed::dates($days, $before_seven));
        $this->assertSame('2026-01-27', DayFeed::dates($days, $before_seven + 60)[0]);

        $many = array_map(fn($i) => ['date' => date('Y-m-d', strtotime('2026-01-01 +' . $i . ' days'))], range(0, 40));
        $this->assertCount(DayFeed::ITEMS, DayFeed::dates($many, strtotime('2026-03-01')));
    }

    public function testSummarizesADay(): void
    {
        $action = fn(string $number, ?string $outcome) => ['bill' => ['number' => $number], 'outcome' => $outcome];
        $day = fn(array $actions) => [
            'chambers' => [['chamber' => 'house', 'floor' => true, 'actions' => $actions]],
            'meetings' => [],
        ];
        $this->assertSame(['The House met in a floor session.', 'One bill moved forward, and 2 were stopped.'],
            Day::summary($day([$action('hb1', 'advanced'), $action('hb2', 'stopped'), $action('hb3', 'stopped')])));
        $this->assertSame('2 bills were stopped.', Day::summary($day([$action('hb2', 'stopped'), $action('hb3', 'stopped')]))[1]);
        $this->assertSame('One bill moved forward.', Day::summary($day([$action('hb1', 'advanced')]))[1]);
        $this->assertCount(1, Day::summary($day([$action('hb1', null)])));
    }

    public function testDescribesADayWithItsHighlights(): void
    {
        DayHighlights::save('2026-01-26');
        $day = Day::record_for_api('2026-01-26');
        $html = DayFeed::description($day);
        $this->assertStringContainsString('<a href="https://www.richmondsunlight.com/bill/2026/hb341/">HB 341</a>', $html);
        $this->assertStringContainsString('Read third time and defeated by House, 45–52.', $html);
        $this->assertStringContainsString('Close vote, 45–52', $html);
        $this->assertStringContainsString('https://www.richmondsunlight.com/day/2026-01-26/', $html);
    }

    public function testMakesValidRss(): void
    {
        $xml = DayFeed::xml([Day::record_for_api('2026-01-26')]);
        $rss = simplexml_load_string($xml);
        $this->assertNotFalse($rss);
        $item = $rss->channel->item[0];
        $this->assertSame('Monday, January 26, 2026', (string) $item->title);
        $this->assertSame('https://www.richmondsunlight.com/day/2026-01-26/', (string) $item->guid);
        $this->assertSame(DayFeed::published_at('2026-01-26'), strtotime((string) $item->pubDate));
        $this->assertStringContainsString('HB 341', (string) $item->description);

        // A feed with no days is still a feed.
        $this->assertNotFalse(simplexml_load_string(DayFeed::xml([])));
    }
}
