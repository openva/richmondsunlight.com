<?php

/**
 * The RSS feed of days in the General Assembly (/rss/days/): one item for each day that the
 * legislature did anything, with its brief (see DayBrief) or how much happened, and the day's
 * highlights, linking to the day's page. See docs/minutes-replacement.md.
 *
 * A day's record fills in over a day or two (votes during the day, video that evening, highlights
 * recomputed overnight), so a day is published at 7 a.m. the next morning, rather than as it
 * happens. Its item doesn't change much after that, and feed readers don't show it again if it
 * does. The day's page always has the latest.
 *
 * Everything comes from the API.
 */
class DayFeed
{
    /** How many days the feed lists. */
    public const ITEMS = 15;

    /** When a day is published: this hour (Richmond's time), the next morning. */
    private const PUBLISH_HOUR = 7;

    /** Where the feed is. */
    public const URL = 'https://www.richmondsunlight.com/rss/days/';

    /**
     * When a day is published in the feed.
     *
     * @param string $date YYYY-MM-DD
     * @return int A timestamp
     */
    public static function published_at(string $date): int
    {
        return (new DateTimeImmutable($date . ' ' . self::PUBLISH_HOUR . ':00', new DateTimeZone('America/New_York')))
            ->modify('+1 day')
            ->getTimestamp();
    }

    /**
     * The dates that the feed lists, newest first: the most recent days with pages that have been
     * published.
     *
     * @param array $days Days from Day::list_from_api(), from any years
     * @param int $now A timestamp
     * @return string[] YYYY-MM-DD
     */
    public static function dates(array $days, int $now): array
    {
        $dates = array_filter(array_column($days, 'date'), fn($date) => self::published_at($date) <= $now);
        rsort($dates);

        return array_slice(array_values(array_unique($dates)), 0, self::ITEMS);
    }

    /**
     * A day, as HTML for a feed reader: how much happened, and its highlights.
     *
     * @param array $day From the API
     * @return string
     */
    public static function description(array $day): string
    {
        $e = fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $html = '';

        // The day's brief, if it has one, and otherwise how much happened.
        $summary = Day::summary($day);
        if (!empty($day['brief'])) {
            $html .= '<p>' . self::absolute(DayBrief::html($day['brief']['text'], $day)) . '</p>'
                . '<p><small>(Written automatically, by a language model, from the day’s records.)</small></p>';
        } elseif (count($summary) > 0) {
            $html .= '<p>' . $e(implode(' ', $summary)) . '</p>';
        }

        $highlights = $day['highlights'] ?? [];
        if (count($highlights) > 0) {
            $html .= '<p><strong>Highlights</strong></p><ol>';
            foreach ($highlights as $highlight) {
                $bill = $highlight['bill'];
                $action = $highlight['action'];
                $number = mb_strtoupper(preg_replace('/^([a-z]+)/', '$1 ', $bill['number']));
                $html .= '<li><a href="' . $e($bill['url']) . '">' . $e($number) . '</a>: ' . $e($bill['catch_line']);
                if (!empty($bill['patron'])) {
                    $html .= ' (' . $e($bill['patron']['name']) . ')';
                }
                $html .= '<br>' . $e($action['text']);
                if (!empty($action['vote']) && !$action['vote']['voice']) {
                    $html .= ', ' . (int) $action['vote']['yes'] . '–' . (int) $action['vote']['no'];
                } elseif (!empty($action['vote'])) {
                    $html .= ', by voice vote';
                }
                $html .= '.';
                if (count($highlight['reasons']) > 0) {
                    $html .= ' <em>' . $e(implode(' · ', $highlight['reasons'])) . '</em>';
                }
                if (!empty($highlight['clip'])) {
                    $html .= ' <a href="' . $e($highlight['clip']['url']) . '">Watch</a>';
                }
                $html .= '</li>';
            }
            $html .= '</ol>';
        }

        return $html . '<p><a href="' . $e($day['url']) . '">Everything that happened on '
            . $e(date('l, F j', strtotime($day['date']))) . '</a></p>';
    }

    /**
     * HTML with this site's links made absolute, as a feed reader needs them.
     */
    private static function absolute(string $html): string
    {
        return preg_replace('/href="\//', 'href="https://www.richmondsunlight.com/', $html);
    }

    /**
     * The feed.
     *
     * @param array $days Days from the API, newest first
     * @return string RSS 2.0
     */
    public static function xml(array $days): string
    {
        $x = fn($text) => htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cdata = fn($text) => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $text) . ']]>';

        $items = '';
        $latest = null;
        foreach ($days as $day) {
            $published = self::published_at($day['date']);
            $latest = max($latest ?? 0, $published);
            $items .= '
		<item>
			<title>' . $x(date('l, F j, Y', strtotime($day['date']))) . '</title>
			<link>' . $x($day['url']) . '</link>
			<guid isPermaLink="true">' . $x($day['url']) . '</guid>
			<pubDate>' . date(DATE_RSS, $published) . '</pubDate>
			<description>' . $cdata(self::description($day)) . '</description>
		</item>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>Richmond Sunlight: Each Day in the General Assembly</title>
		<link>https://www.richmondsunlight.com/day/</link>
		<atom:link href="' . self::URL . '" rel="self" type="application/rss+xml" />
		<description>What the Virginia General Assembly did each day, and its highlights, the morning after.</description>
		<language>en-us</language>' . ($latest !== null ? '
		<lastBuildDate>' . date(DATE_RSS, $latest) . '</lastBuildDate>' : '') . $items . '
	</channel>
</rss>
';
    }
}
