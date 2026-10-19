<?php

use PHPUnit\Framework\TestCase;

/**
 * The base for tests in the `local` suite, which run against the local-only sample of the
 * database (deploy/mysql/local-test-records.sql, exported by deploy/database_export.sh) on top of
 * the committed test data.
 *
 * Tests are skipped when there's no database, or when the local sample isn't loaded. Because the
 * local database changes (it's re-exported, and rs-video-processor writes to it), tests here
 * should check things that must be true of any data, rather than expecting particular records.
 */
abstract class LocalTestCase extends TestCase
{
    /**
     * The committed test data has about 50 bills; the local sample has a few hundred.
     */
    protected const LOCAL_BILL_COUNT = 150;

    private static ?bool $dbAvailable = null;
    private static ?bool $localDataLoaded = null;

    protected function setUp(): void
    {
        if (!self::connect()) {
            $this->markTestSkipped('Database not available');
        }
        if ($this->requiresLocalData() && !self::localDataLoaded()) {
            $this->markTestSkipped(
                'The local test data isn’t loaded; see “Local tests” in README.md'
            );
        }
    }

    /**
     * Whether the tests in this class need the local sample (rather than just a database).
     */
    protected function requiresLocalData(): bool
    {
        return true;
    }

    protected static function connect(): bool
    {
        if (self::$dbAvailable === null) {
            $database = new Database();
            self::$dbAvailable = $database->connect_mysqli() !== false;
        }
        return self::$dbAvailable;
    }

    protected static function localDataLoaded(): bool
    {
        if (self::$localDataLoaded === null) {
            self::$localDataLoaded = (int) self::value('SELECT COUNT(*) FROM bills') >= self::LOCAL_BILL_COUNT;
        }
        return self::$localDataLoaded;
    }

    /**
     * Run a query, returning all of its rows.
     */
    protected static function rows(string $sql): array
    {
        $result = mysqli_query($GLOBALS['db'], $sql);
        if ($result === false) {
            throw new RuntimeException('Query failed: ' . mysqli_error($GLOBALS['db']) . "\n" . $sql);
        }
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    /**
     * Run a query, returning the first column of every row.
     */
    protected static function column(string $sql): array
    {
        return array_map(fn($row) => reset($row), self::rows($sql));
    }

    /**
     * Run a query, returning the first column of the first row.
     */
    protected static function value(string $sql)
    {
        return self::column($sql)[0] ?? null;
    }

    /**
     * Assert that a sweep found no problems, listing every one it did find.
     *
     * @param string[] $problems
     * @param string $subject What was swept (e.g., "bill pages")
     */
    protected function assertNoProblems(array $problems, int $checked, string $subject): void
    {
        $this->assertGreaterThan(0, $checked, 'There were no ' . $subject . ' to check');
        $this->assertSame(
            [],
            $problems,
            count($problems) . ' problems in ' . $checked . ' ' . $subject . ":\n" . implode("\n", $problems)
        );
    }
}
