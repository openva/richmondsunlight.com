<?php

use PHPUnit\Framework\TestCase;

/**
 * Bill2::getid()'s cache of the current session's bill IDs, which rs-machine's cron/cache.php also
 * fills. Bill numbers repeat every session, so the key includes the year ("bill-2025-hb1"); without
 * it, a bill from the new session that hadn't been filed yet was found as last year's.
 */
class BillIdCacheTest extends TestCase
{
    public function testTheCurrentSessionsBillsAreLookedUpByYearAndNumber(): void
    {
        $bill = $this->bill(['bill-' . SESSION_YEAR . '-hb1' => 12345]);

        $this->assertSame(12345, $bill->getid(SESSION_YEAR, 'HB1'));
        $this->assertSame(['bill-' . SESSION_YEAR . '-hb1'], $bill->cache->requested);
    }

    /**
     * A number cached without its year, as it once was, isn't used.
     */
    public function testANumberCachedWithoutItsYearIsntUsed(): void
    {
        $bill = $this->bill(['bill-hb1' => 12345]);

        $this->assertNotSame(12345, @$bill->getid(SESSION_YEAR, 'hb1'));
    }

    /**
     * @param array<string, int> $cached What Memcached holds.
     */
    private function bill(array $cached): Bill2
    {
        if (!class_exists('Memcached')) {
            $this->markTestSkipped('The Memcached extension is not installed');
        }

        $cache = new class ($cached) extends Memcached {
            public array $requested = [];
            private int $code = Memcached::RES_NOTFOUND;

            public function __construct(private array $cached)
            {
            }

            public function get(string $key, ?callable $cache_cb = null, int $get_flags = 0): mixed
            {
                $this->requested[] = $key;
                $this->code = array_key_exists($key, $this->cached) ? Memcached::RES_SUCCESS : Memcached::RES_NOTFOUND;
                return $this->cached[$key] ?? false;
            }

            public function getResultCode(): int
            {
                return $this->code;
            }
        };

        return new class ($cache) extends Bill2 {
            public function __construct(public $cache)
            {
            }

            protected function getCache(): ?Memcached
            {
                return $this->cache;
            }
        };
    }
}
