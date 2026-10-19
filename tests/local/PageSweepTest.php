<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Load every bill, legislator, committee, and minutes page in the local database, and fail on any
 * that don't return 200, or that raise a PHP warning (or notice, or error).
 *
 * Pages don't show PHP errors, so this reads them from the log (PHP_ERROR_LOG, set in
 * deploy/settings-docker.inc.php). Pages are loaded one at a time, so that each error can be
 * attributed to the page that raised it. Memcached is emptied first, so that cached data doesn't
 * spare any code from running.
 */
#[Group('local')]
class PageSweepTest extends LocalTestCase
{
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim(getenv('RS_TEST_BASE_URL') ?: 'http://localhost', '/');

        if (class_exists('Memcached')) {
            $mc = new Memcached();
            $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
            $mc->flush();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('PHP_ERROR_LOG') || !is_writable(dirname(PHP_ERROR_LOG))) {
            $this->markTestSkipped('PHP isn’t logging errors to a file (see PHP_ERROR_LOG)');
        }
    }

    public function testBillPages(): void
    {
        $this->sweep(self::column('SELECT CONCAT("/bill/", sessions.year, "/", bills.number, "/")
            FROM bills
            LEFT JOIN sessions
                ON bills.session_id = sessions.id
            ORDER BY bills.id'), 'bill pages');
    }

    public function testBillTextPages(): void
    {
        $this->sweep(self::column('SELECT DISTINCT CONCAT("/bill/", sessions.year, "/", bills.number, "/fulltext/")
            FROM bills
            LEFT JOIN sessions
                ON bills.session_id = sessions.id
            WHERE bills.id IN (SELECT bill_id FROM bills_full_text WHERE text IS NOT NULL)
            ORDER BY bills.id'), 'bill text pages');
    }

    public function testLegislatorPages(): void
    {
        $this->sweep(self::column('SELECT CONCAT("/legislator/", people.shortname, "/")
            FROM people
            WHERE people.id IN (SELECT person_id FROM terms)
            ORDER BY people.id'), 'legislator pages');
    }

    public function testCommitteePages(): void
    {
        $this->sweep(self::column('SELECT CONCAT("/committee/", chamber, "/", shortname, "/")
            FROM committees
            WHERE parent_id IS NULL AND date_ended IS NULL
            ORDER BY id'), 'committee pages');
    }

    public function testMinutesPages(): void
    {
        $this->sweep(self::column('SELECT CONCAT("/minutes/", chamber, "/", DATE_FORMAT(date, "%Y/%m/%d"), "/")
            FROM (
                SELECT chamber, date
                FROM minutes
                UNION
                SELECT chamber, date
                FROM files
                WHERE chamber IN ("house", "senate") AND title LIKE "%Session%"
                    AND id IN (SELECT file_id FROM video_index)
            ) AS days
            ORDER BY date, chamber'), 'minutes pages');
    }

    /**
     * Load each page, collecting every problem found.
     *
     * @param string[] $paths
     */
    private function sweep(array $paths, string $subject): void
    {
        $problems = [];
        foreach ($paths as $path) {
            $log_offset = self::logSize();
            [$status, $body] = self::fetch($path);

            if ($status !== 200) {
                $problems[] = $path . ' returned ' . ($status ?: 'no response');
            }
            if (preg_match('#<b>(Fatal error|Warning|Notice|Deprecated)</b>:#', $body, $match)) {
                $problems[] = $path . ' displayed a PHP ' . strtolower($match[1]);
            }
            foreach (self::logLinesSince($log_offset) as $line) {
                $problems[] = $path . ' logged ' . $line;
            }
        }

        $this->assertNoProblems($problems, count($paths), $subject);
    }

    /**
     * @return array The HTTP status (0 if there was no response) and the body
     */
    private static function fetch(string $path): array
    {
        $ch = curl_init(self::$baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [(int) $status, (string) $body];
    }

    private static function logSize(): int
    {
        clearstatcache(true, PHP_ERROR_LOG);
        return file_exists(PHP_ERROR_LOG) ? filesize(PHP_ERROR_LOG) : 0;
    }

    /**
     * The PHP errors logged after a point in the log, without timestamps. SimplePie logs a
     * "notice" whenever a feed can't be loaded, which is a problem with a legislator's website,
     * rather than with this site, so those are left out.
     *
     * @return string[]
     */
    private static function logLinesSince(int $offset): array
    {
        if (self::logSize() <= $offset) {
            return [];
        }
        $lines = [];
        $log = file_get_contents(PHP_ERROR_LOG, false, null, $offset);
        foreach (preg_split('/\R/', trim($log)) as $line) {
            if ($line === '' || stripos($line, '/vendor/simplepie/') !== false) {
                continue;
            }
            $lines[] = preg_replace(['/^\[[^\]]+\]\s*/', '#/var/www/htdocs/#'], ['', ''], $line);
        }
        return $lines;
    }
}
