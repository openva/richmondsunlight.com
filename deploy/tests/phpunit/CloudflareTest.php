<?php

use PHPUnit\Framework\TestCase;

/**
 * Cloudflare, which expires Cloudflare's copies of a bill's page and the home page when a comment
 * or tag is added or removed. Locally, no API token is set, so nothing is sent to Cloudflare.
 */
class CloudflareTest extends TestCase
{
    /** A directory standing in for Cloudflare's API (see respond()). */
    private string $api;

    protected function setUp(): void
    {
        $database = new Database();
        $database->connect_mysqli();
        $this->api = sys_get_temp_dir() . '/cloudflare-test-' . bin2hex(random_bytes(4));
        mkdir($this->api . '/zone1', 0777, true);
    }

    protected function tearDown(): void
    {
        Cloudflare::use();
        foreach (glob($this->api . '/zone1/*') as $file) {
            unlink($file);
        }
        rmdir($this->api . '/zone1');
        rmdir($this->api);
    }

    /**
     * Have Cloudflare's API give this response. Its requests go to a file (by a file:// URL),
     * which holds the response, instead of to Cloudflare.
     */
    private function respond(?string $response): void
    {
        Cloudflare::use('token1', 'zone1', 'file://' . $this->api . '/');
        if ($response !== null) {
            file_put_contents($this->api . '/zone1/purge_cache', $response);
        }
    }

    public function testPurgesThroughCloudflaresApi(): void
    {
        $this->respond('{"success":true,"errors":[],"result":{"id":"zone1"}}');
        $this->assertTrue(Cloudflare::enabled());
        $this->assertTrue(Cloudflare::purge(['/']));
        $this->assertTrue(Cloudflare::purge_bills([72883]));

        // More than 30 URLs take more than one request.
        $this->assertTrue(Cloudflare::purge(array_map(fn($i) => '/bill/2024/hb' . $i . '/', range(1, 20))));
    }

    public function testSaysWhenAPurgeFails(): void
    {
        // Cloudflare refused.
        $this->respond('{"success":false,"errors":[{"code":10000,"message":"Authentication error"}]}');
        $this->assertFalse(Cloudflare::purge(['/']));

        // Cloudflare's answer made no sense.
        $this->respond('Bad gateway');
        $this->assertFalse(Cloudflare::purge(['/']));
    }

    public function testSaysWhenCloudflareCantBeReached(): void
    {
        $this->respond(null);
        $this->assertFalse(Cloudflare::purge(['/']));
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
