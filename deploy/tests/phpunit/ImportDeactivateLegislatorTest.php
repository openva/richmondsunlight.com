<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::deactivate_legislator(), which marks a legislator who's left office.
 */
class ImportDeactivateLegislatorTest extends TestCase
{
    /** An LIS number that no real legislator has. */
    private const LIS_ID = 999;

    private PDO $dbh;

    protected function setUp(): void
    {
        $database = new Database();
        $dbh = @$database->connect();
        if (!($dbh instanceof PDO)) {
            $this->markTestSkipped('Database not available');
        }
        $this->dbh = $dbh;
        $GLOBALS['dbh'] = $dbh;
        $dbh->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->dbh) && $this->dbh->inTransaction()) {
            $this->dbh->rollBack();
        }
    }

    /**
     * House and Senate numbers overlap: H0108 and S0108 are different legislators.
     */
    public function testEndsOnlyTheTermInTheNamedChamber(): void
    {
        $house = $this->insertTerm('house');
        $senate = $this->insertTerm('senate');

        $this->assertTrue($this->importOn('2026-06-10')->deactivate_legislator('S0' . self::LIS_ID));

        $this->assertSame('2026-06-09', $this->dateEnded($senate));
        $this->assertNull($this->dateEnded($house), 'The delegate with the same number is still serving.');
    }

    public function testAcceptsUnpaddedAndPaddedIds(): void
    {
        $house = $this->insertTerm('house');

        $this->assertTrue($this->importOn('2026-06-10')->deactivate_legislator('H00' . self::LIS_ID));
        $this->assertSame('2026-06-09', $this->dateEnded($house));
    }

    /**
     * Without the prefix, there's no knowing which chamber's legislator is meant.
     */
    public function testRejectsAnIdWithoutAChamber(): void
    {
        $house = $this->insertTerm('house');

        $this->assertFalse($this->importOn('2026-06-10')->deactivate_legislator((string) self::LIS_ID));
        $this->assertNull($this->dateEnded($house));
    }

    public function testReportsWhenNoTermWasOpen(): void
    {
        $this->assertFalse($this->importOn('2026-06-10')->deactivate_legislator('H0' . self::LIS_ID));
    }

    /**
     * After November's election in an odd year, departing legislators serve until the next
     * session starts. This failed under the task runner, where $GLOBALS['db'] is mysqli.
     */
    public function testAfterAnElectionTheTermEndsWhenTheNextSessionStarts(): void
    {
        $GLOBALS['db'] = new class {
            public function __call($name, $arguments)
            {
                throw new LogicException('Import must use $GLOBALS[\'dbh\'], not $GLOBALS[\'db\']');
            }
        };
        $this->insertSession('2100-08-01');
        $this->insertSession('2100-01-13');
        $house = $this->insertTerm('house');

        $this->assertTrue($this->importOn('2099-11-15')->deactivate_legislator('H0' . self::LIS_ID));
        $this->assertSame('2100-01-13', $this->dateEnded($house));
    }

    public function testAfterAnElectionWithNoKnownSessionTheTermEndsOnJanuaryFirst(): void
    {
        $house = $this->insertTerm('house');

        $this->assertTrue($this->importOn('2099-11-15')->deactivate_legislator('H0' . self::LIS_ID));
        $this->assertSame('2100-01-01', $this->dateEnded($house));
    }

    /**
     * A legislator who leaves after an election, but before the next session (by dying, or by
     * resigning), left when LIS says.
     */
    public function testUsesTheDateLisGives(): void
    {
        $this->insertSession('2100-01-13');
        $house = $this->insertTerm('house');
        $member = ['MemberNumber' => 'H0' . self::LIS_ID, 'ServiceEndDate' => '2099-11-20T00:00:00'];

        $this->assertTrue($this->importOn('2099-11-25', $member)->deactivate_legislator('H0' . self::LIS_ID));
        $this->assertSame('2099-11-20', $this->dateEnded($house));
    }

    /**
     * @param array<string,mixed> $member What LIS says about the member.
     */
    private function importOn(string $date, array $member = []): Import
    {
        return new class (new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        }, new DateTimeImmutable($date), $member) extends Import {
            public function __construct(Log $log, private DateTimeImmutable $today, private array $member)
            {
                parent::__construct($log);
            }

            protected function now(): DateTimeImmutable
            {
                return $this->today;
            }

            protected function lis_api_request($path, array $query = [])
            {
                return $this->member === [] ? [] : ['Members' => [$this->member]];
            }
        };
    }

    private function insertTerm(string $chamber): int
    {
        $this->dbh->prepare(
            'INSERT INTO terms
                (person_id, name_formatted, lis_shortname, lis_id, chamber, district_id, date_started,
                 partisanship, date_created, date_modified)
             VALUES (1, "Test", "test", :lis_id, :chamber, 1, "2024-01-10", 0, NOW(), NOW())'
        )->execute([':lis_id' => self::LIS_ID, ':chamber' => $chamber]);

        return (int) $this->dbh->lastInsertId();
    }

    private function insertSession(string $date_started): void
    {
        $this->dbh->prepare(
            'INSERT INTO sessions (year, date_started, notes, date_created)
             VALUES (:year, :date_started, "", NOW())'
        )->execute([':year' => (int) substr($date_started, 0, 4), ':date_started' => $date_started]);
    }

    private function dateEnded(int $term_id): ?string
    {
        $stmt = $this->dbh->prepare('SELECT date_ended FROM terms WHERE id = :id');
        $stmt->execute([':id' => $term_id]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }
}
