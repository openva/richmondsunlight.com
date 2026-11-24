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

    /**
     * A budget bill's history lists hundreds of the governor's amendments in one entry, more than
     * bills_status.status holds. It's shortened, the same way each time it's fetched.
     */
    public function testAnEntryTooLongToStoreIsShortened(): void
    {
        $status = 'House passed by Governor\'s amendments #`s ' . implode(', ', range(1, 120));
        $event = ['status' => $status, 'chamber' => 'house', 'date' => '2025-04-02 12:00:00'];
        $import = $this->import();

        $this->assertSame(1, $import->store_status_history(self::BILL_ID, [$event], 1));
        $this->assertSame(1, $import->store_status_history(self::BILL_ID, [$event], 1));

        $stored = $this->dbh->query('SELECT status FROM bills_status WHERE bill_id = ' . self::BILL_ID)
            ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(1, $stored);
        $this->assertSame(255, mb_strlen($stored[0]));
        $this->assertStringStartsWith('House passed by Governor\'s amendments #`s 1, 2, 3', $stored[0]);
        $this->assertStringEndsWith('…', $stored[0]);
    }

    /**
     * LIS's CSV dated entries at midnight, and its API gives the same entries times. Fetched from
     * the API, an entry from the CSV is the same entry: it takes the API's time, and keeps its
     * translation and when it was recorded.
     */
    public function testTheSameEntryWithATimeIsNotStoredAgain(): void
    {
        $import = $this->import();
        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Left in Finance', 'chamber' => 'house', 'date' => '2025-02-04 00:00:00'],
        ], 1);
        $this->dbh->exec('UPDATE bills_status SET translation = "failed committee",
            date_created = "2025-02-05 02:02:03" WHERE bill_id = ' . self::BILL_ID);

        $this->assertSame(1, $import->store_status_history(self::BILL_ID, [
            ['status' => 'Left in Finance', 'chamber' => 'house', 'date' => '2025-02-04 14:18:00'],
        ], 1));

        $this->assertSame([[
            'date' => '2025-02-04 14:18:00',
            'translation' => 'failed committee',
            'date_created' => '2025-02-05 02:02:03',
        ]], $this->dates());
    }

    /**
     * LIS gives some entries only a date, and changes some entries' times. A time isn't replaced
     * with midnight.
     */
    public function testATimeIsNotReplacedWithMidnight(): void
    {
        $import = $this->import();
        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Reported from Finance (12-Y 3-N)', 'date' => '2026-01-20 10:00:00'],
        ], 1);

        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Reported from Finance (12-Y 3-N)', 'date' => '2026-01-20 00:00:00'],
        ], 1);
        $this->assertSame(['2026-01-20 10:00:00'], array_column($this->dates(), 'date'));

        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Reported from Finance (12-Y 3-N)', 'date' => '2026-01-20 14:30:00'],
        ], 1);
        $this->assertSame(['2026-01-20 14:30:00'], array_column($this->dates(), 'date'));
    }

    /**
     * LIS's CSV began some entries with a space, and its API adds "Block Vote" to some. The stored
     * entry takes LIS's current wording.
     */
    public function testTheSameEntryWordedDifferentlyIsNotStoredAgain(): void
    {
        $import = $this->import();
        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Passed Senate (40-Y 0-N)', 'date' => '2025-01-28 00:00:00'],
            ['status' => ' Governor\'s Action Deadline 11:59 p.m., May 2, 2025', 'date' => '2025-04-02 00:00:00'],
        ], 1);

        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Passed Senate  Block Vote (40-Y 0-N)', 'date' => '2025-01-28 12:01:00'],
            ['status' => 'Governor\'s Action Deadline 11:59 p.m., May 2, 2025', 'date' => '2025-04-02 00:00:00'],
        ], 1);

        $this->assertSame(
            ['Passed Senate  Block Vote (40-Y 0-N)', 'Governor\'s Action Deadline 11:59 p.m., May 2, 2025'],
            array_column($this->dbh->query('SELECT status FROM bills_status WHERE bill_id = ' . self::BILL_ID
                . ' ORDER BY date')->fetchAll(PDO::FETCH_ASSOC), 'status')
        );
    }

    public function testTheSameTextOnAnotherDayIsAnotherEntry(): void
    {
        $import = $this->import();
        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Passed by for the day', 'date' => '2026-02-10 12:00:00'],
            ['status' => 'Passed by for the day', 'date' => '2026-02-11 12:00:00'],
        ], 1);
        $import->store_status_history(self::BILL_ID, [
            ['status' => 'Passed by for the day', 'date' => '2026-02-11 12:05:00'],
        ], 1);

        $this->assertSame(
            ['2026-02-10 12:00:00', '2026-02-11 12:05:00'],
            array_column($this->dates(), 'date')
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function dates(): array
    {
        return $this->dbh->query('SELECT date, translation, date_created FROM bills_status
            WHERE bill_id = ' . self::BILL_ID . ' ORDER BY date')->fetchAll(PDO::FETCH_ASSOC);
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
