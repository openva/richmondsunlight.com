<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Example local test — establishes the pattern for the `local` group.
 *
 * Tests in this class (and any other class tagged #[Group('local')]) are
 * committed to git but only executed locally, where a richer SQL dataset is
 * loaded alongside the standard seeded data.
 *
 * To run local tests:
 *   ./htdocs/includes/vendor/bin/phpunit --testsuite local
 */
#[Group('local')]
class LocalExampleTest extends TestCase
{
    private static bool $dbAvailable = false;

    public static function setUpBeforeClass(): void
    {
        $database = new Database();
        if ($database->connect_mysqli() === false) {
            return;
        }
        self::$dbAvailable = true;
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
    }

    /**
     * Example: verify the local dataset has a full bill count for the current
     * session. The seeded test data contains only a handful of bills; a real
     * local dataset has hundreds. Replace or extend this with your own
     * local-data assertions.
     */
    public function testCurrentSessionHasSubstantialBillCount(): void
    {
        $sql = 'SELECT COUNT(*) as count FROM bills WHERE year = ' . SESSION_YEAR;
        $result = mysqli_query($GLOBALS['db'], $sql);
        $row = mysqli_fetch_assoc($result);
        $this->assertGreaterThan(
            100,
            (int) $row['count'],
            'Expected full local dataset with 100+ bills for ' . SESSION_YEAR . '; load your local SQL data file first'
        );
    }
}
