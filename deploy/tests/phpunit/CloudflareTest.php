<?php

use PHPUnit\Framework\TestCase;

/**
 * Cloudflare, which expires Cloudflare's copies of a bill's page and the home page when a comment
 * or tag is added or removed. Locally, no API token is set, so nothing is sent to Cloudflare.
 */
class CloudflareTest extends TestCase
{
    protected function setUp(): void
    {
        $database = new Database();
        $database->connect_mysqli();
    }

    public function testIsOffWithoutAnApiToken(): void
    {
        $this->assertFalse(Cloudflare::enabled());
        $this->assertFalse(Cloudflare::purge(['/']));
        $this->assertFalse(Cloudflare::purge_bills([72883]));
    }

    /**
     * Cloudflare keeps http and https copies of a page separately.
     */
    public function testPurgesEachPathOverHttpAndHttps(): void
    {
        $host = parse_url(SITE_BASE_URL, PHP_URL_HOST);

        $this->assertSame([
            'https://' . $host . '/',
            'http://' . $host . '/',
            'https://' . $host . '/bill/2024/hb1/',
            'http://' . $host . '/bill/2024/hb1/',
        ], Cloudflare::urls(['/', '/bill/2024/hb1/', '/']));
    }

    public function testFindsBillsPagesByTheirIds(): void
    {
        $paths = Cloudflare::bill_paths([72884, '72883', 72883]);
        sort($paths);

        $this->assertSame(['/bill/2024/hb1/', '/bill/2024/hb2/'], $paths);
    }

    public function testAMissingBillHasNoPage(): void
    {
        $this->assertSame([], Cloudflare::bill_paths([0]));
        $this->assertSame([], Cloudflare::bill_paths([]));
    }
}
