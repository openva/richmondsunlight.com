<?php

use PHPUnit\Framework\TestCase;

/**
 * Page::is_archival(), which decides whether a page is about a session that's long over, and so
 * may be cached for a week rather than 10 minutes.
 */
class PageArchivalTest extends TestCase
{
    private static function page($session_year): Page
    {
        $page = new Page();
        $page->session_year = $session_year;

        return $page;
    }

    public function testASessionFromYearsAgoIsArchival(): void
    {
        $this->assertTrue(self::page(SESSION_YEAR - 5)->is_archival());
    }

    public function testTheSessionBeforeLastIsArchival(): void
    {
        $this->assertTrue(self::page(SESSION_YEAR - 2)->is_archival());
    }

    /**
     * A special session may still be under way, or continued bills and video still arriving.
     */
    public function testLastYearsSessionIsNotArchival(): void
    {
        $this->assertFalse(self::page(SESSION_YEAR - 1)->is_archival());
    }

    public function testTheCurrentSessionIsNotArchival(): void
    {
        $this->assertFalse(self::page(SESSION_YEAR)->is_archival());
    }

    public function testAPageAboutNoSessionIsNotArchival(): void
    {
        $this->assertFalse(self::page(null)->is_archival());
    }

    /**
     * The year comes from the URL on some pages.
     */
    public function testAYearAsAStringIsArchival(): void
    {
        $this->assertTrue(self::page((string) (SESSION_YEAR - 3))->is_archival());
    }

    public function testAYearThatIsNotANumberIsNotArchival(): void
    {
        $this->assertFalse(self::page('2010abc')->is_archival());
    }
}
