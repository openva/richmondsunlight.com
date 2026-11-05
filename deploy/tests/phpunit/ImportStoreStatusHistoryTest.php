<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::store_status_history(), which rs-machine calls each time it fetches a bill's history.
 */
class ImportStoreStatusHistoryTest extends TestCase
{
    /** A bill ID that no real bill has: the largest a mediumint holds, but one. */
    private const BILL_ID = 16777214;

    private PDO $dbh;

    protected function setUp(): void
    {
        $database = new Database();
        $dbh = @$database->connect();
        if (!($dbh instanceof PDO)) {
            $this->markTestSkipped('Database not available');
        }
        $this->dbh = $dbh;
        $dbh->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->dbh) && $this->dbh->inTransaction()) {
            $this->dbh->rollBack();
        }
    }

    /**
     * LIS's history has no translations; rs-machine's cleanup adds them, and the website shows
     * them. Fetching the history again must not erase them.
     */
    public function testFetchingAgainKeepsTheTranslation(): void
    {
        $event = ['status' => 'Reported from Finance (12-Y 3-N)', 'chamber' => 'house',
            'date' => '2026-01-20 10:00:00', 'lis_vote_id' => 'H1234'];
        $import = $this->import();

        $this->assertSame(1, $import->store_status_history(self::BILL_ID, [$event], 1));
        $this->dbh->exec('UPDATE bills_status SET translation = "passed committee"
            WHERE bill_id = ' . self::BILL_ID);

        $this->assertSame(1, $import->store_status_history(self::BILL_ID, [$event], 1));

        $this->assertSame(
            [['translation' => 'passed committee', 'lis_vote_id' => 'H1234', 'chamber' => 'house']],
            $this->stored()
        );
    }

    public function testAValueLisNoLongerGivesIsKept(): void
    {
        $event = ['status' => 'Passed House', 'chamber' => 'house', 'date' => '2026-02-01 12:00:00',
            'lis_vote_id' => 'H2345'];
        $import = $this->import();
        $import->store_status_history(self::BILL_ID, [$event], 1);

        $import->store_status_history(self::BILL_ID, [['lis_vote_id' => null, 'chamber' => null] + $event], 1);

        $this->assertSame([['translation' => null, 'lis_vote_id' => 'H2345', 'chamber' => 'house']], $this->stored());
    }

    private function import(): Import
    {
        $import = new Import(new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        });

        // Use this test's connection, so that its transaction covers what Import writes.
        $property = new ReflectionProperty(Import::class, 'pdo');
        $property->setAccessible(true);
        $property->setValue($import, $this->dbh);

        return $import;
    }

    /** @return array<int,array<string,mixed>> */
    private function stored(): array
    {
        return $this->dbh->query('SELECT translation, lis_vote_id, chamber FROM bills_status
            WHERE bill_id = ' . self::BILL_ID)->fetchAll(PDO::FETCH_ASSOC);
    }
}
