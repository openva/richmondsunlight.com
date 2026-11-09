<?php

use PHPUnit\Framework\TestCase;

/**
 * What Database does when it can't connect: the site sends the browser to /site-down/, but the API
 * and command-line scripts get false, to handle the failure themselves.
 */
class DatabaseConnectionFailureTest extends TestCase
{
    private ?string $host;

    protected function setUp(): void
    {
        $this->host = $_SERVER['HTTP_HOST'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->host === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $this->host;
        }
    }

    private static function isApiRequest(?string $host): bool
    {
        if ($host === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $host;
        }

        return (new ReflectionMethod(Database::class, 'is_api_request'))->invoke(null);
    }

    public function testTheApiIsRecognizedByItsHost(): void
    {
        $this->assertTrue(self::isApiRequest('api.richmondsunlight.com'));
        $this->assertTrue(self::isApiRequest('API.richmondsunlight.com:443'));
    }

    public function testTheSiteIsNotTheApi(): void
    {
        $this->assertFalse(self::isApiRequest('www.richmondsunlight.com'));
        $this->assertFalse(self::isApiRequest('richmondsunlight.com'));
    }

    /**
     * The host is in the URL that the site uses to reach the API, e.g., "api" in Docker.
     */
    public function testTheApiIsRecognizedByTheHostInApiUrl(): void
    {
        $api_host = parse_url((string) API_URL, PHP_URL_HOST);
        if (empty($api_host)) {
            $this->markTestSkipped('API_URL has no host');
        }

        $this->assertTrue(self::isApiRequest($api_host));
    }

    public function testNoHostIsNotTheApi(): void
    {
        $this->assertFalse(self::isApiRequest(null));
    }

    /**
     * On the command line, there's no browser to redirect, so a failure is returned.
     */
    public function testAFailureOnTheCommandLineReturnsFalse(): void
    {
        $_SERVER['HTTP_HOST'] = 'www.richmondsunlight.com';

        $this->assertFalse((new ReflectionMethod(Database::class, 'connection_failed'))->invoke(null));
    }
}
