<?php

/**
 * Builds RSS 2.0 feeds (https://www.rssboard.org/rss-specification), for the feeds in htdocs/rss/
 * and Photosynthesis's. (The feed of days has its own, in DayFeed.)
 */
class Rss
{
    /**
     * An item in a feed.
     *
     * @param string $title Plain text
     * @param string $link An absolute URL
     * @param string $description HTML
     * @param string|null $guid What identifies the item, if not its link (which isn't unique when
     *     a feed has several items about one bill, say)
     * @param int|null $date When it happened, as a timestamp
     * @return string
     */
    public static function item(string $title, string $link, string $description, ?string $guid = null, ?int $date = null): string
    {
        $item = '
		<item>
			<title>' . self::text($title) . '</title>
			<link>' . self::text($link) . '</link>
			<description>' . self::cdata($description) . '</description>';
        if ($guid !== null) {
            $item .= '
			<guid isPermaLink="false">' . self::text($guid) . '</guid>';
        } else {
            $item .= '
			<guid isPermaLink="true">' . self::text($link) . '</guid>';
        }
        if ($date !== null) {
            $item .= '
			<pubDate>' . date(DATE_RSS, $date) . '</pubDate>';
        }

        return $item . '
		</item>';
    }

    /**
     * A feed.
     *
     * @param string $title Plain text
     * @param string $link The page that the feed is about, as an absolute URL
     * @param string $self The feed's own URL
     * @param string $description Plain text
     * @param string $items From item()
     * @param int|null $date When it last changed, as a timestamp
     * @return string
     */
    public static function feed(string $title, string $link, string $self, string $description, string $items, ?int $date = null): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>' . self::text($title) . '</title>
		<link>' . self::text($link) . '</link>
		<atom:link href="' . self::text($self) . '" rel="self" type="application/rss+xml" />
		<description>' . self::text($description) . '</description>
		<language>en-us</language>' . ($date !== null ? '
		<lastBuildDate>' . date(DATE_RSS, $date) . '</lastBuildDate>' : '') . $items . '
	</channel>
</rss>
';
    }

    /**
     * A timestamp from a date in the database (in Richmond's time), or null if there isn't one.
     */
    public static function timestamp(?string $date): ?int
    {
        if (empty($date) || str_starts_with($date, '0000')) {
            return null;
        }
        $timestamp = strtotime($date);

        return $timestamp === false ? null : $timestamp;
    }

    private static function text(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function cdata(string $html): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . ']]>';
    }
}
