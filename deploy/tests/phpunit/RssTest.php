<?php

use PHPUnit\Framework\TestCase;

/**
 * The RSS 2.0 feeds built by Rss (all but the feed of days, which DayFeedTest covers).
 */
class RssTest extends TestCase
{
    public function testBuildsAValidFeed(): void
    {
        $items = Rss::item('Fish & “chips” <HB1>', 'https://www.richmondsunlight.com/bill/2025/hb1/', '<p>A summary.</p>', 'status-1', strtotime('2025-01-15 10:00'))
            . Rss::item('Another', 'https://www.richmondsunlight.com/bill/2025/hb2/', 'Ends with ]]> in it');
        $xml = Rss::feed('A & B', 'https://www.richmondsunlight.com/', 'https://www.richmondsunlight.com/rss/comments/', 'About it.', $items, strtotime('2025-01-15 10:00'));

        $feed = simplexml_load_string($xml);
        $this->assertNotFalse($feed);
        $this->assertSame('2.0', (string) $feed['version']);
        $this->assertSame('A & B', (string) $feed->channel->title);
        $this->assertSame('Wed, 15 Jan 2025 10:00:00 -0500', (string) $feed->channel->lastBuildDate);
        $this->assertSame(
            'https://www.richmondsunlight.com/rss/comments/',
            (string) $feed->channel->children('http://www.w3.org/2005/Atom')->link->attributes()->href
        );

        $first = $feed->channel->item[0];
        $this->assertSame('Fish & “chips” <HB1>', (string) $first->title);
        $this->assertSame('<p>A summary.</p>', (string) $first->description);
        $this->assertSame('status-1', (string) $first->guid);
        $this->assertSame('false', (string) $first->guid['isPermaLink']);
        $this->assertSame('Wed, 15 Jan 2025 10:00:00 -0500', (string) $first->pubDate);

        // Without a guid, the link is the guid, and without a date, there's no pubDate.
        $second = $feed->channel->item[1];
        $this->assertSame('https://www.richmondsunlight.com/bill/2025/hb2/', (string) $second->guid);
        $this->assertSame('true', (string) $second->guid['isPermaLink']);
        $this->assertCount(0, $second->pubDate);
        $this->assertSame('Ends with ]]> in it', (string) $second->description);
    }

    public function testReadsDatesFromTheDatabase(): void
    {
        $this->assertSame(strtotime('2025-01-15 10:00 America/New_York'), Rss::timestamp('2025-01-15 10:00:00'));
        $this->assertNull(Rss::timestamp(null));
        $this->assertNull(Rss::timestamp(''));
        $this->assertNull(Rss::timestamp('0000-00-00 00:00:00'));
    }
}
