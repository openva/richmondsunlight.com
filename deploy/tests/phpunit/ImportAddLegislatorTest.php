<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::add_legislator()'s choice of when a new term started.
 *
 * add_legislator() uses its own transaction, so this deletes what it creates, rather than
 * rolling it back.
 */
class ImportAddLegislatorTest extends TestCase
{
    /** An LIS number that no real legislator has. */
    private const LIS_ID = 998;

    /** Far enough away that this test's session is the latest. */
    private const TODAY = '2098-01-20';
    private const SESSION_START = '2098-01-14';

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
        $this->cleanUp();

        $dbh->prepare('INSERT INTO sessions (year, date_started, notes, date_created)
            VALUES (2098, :date_started, "test", NOW())')->execute([':date_started' => self::SESSION_START]);
    }

    protected function tearDown(): void
    {
        if (isset($this->dbh)) {
            $this->cleanUp();
        }
    }

    private function cleanUp(): void
    {
        $this->dbh->exec('DELETE FROM terms WHERE lis_id = ' . self::LIS_ID);
        $this->dbh->exec('DELETE FROM people WHERE shortname = "zztestlegislator"');
        $this->dbh->exec('DELETE FROM sessions WHERE year = 2098 AND notes = "test"');
    }

    /**
     * LIS gives the date that a member first took office. For someone returning after time away,
     * that's during their earlier term, and the new term starts with the current session.
     */
    public function testAReturningLegislatorsTermStartsWithTheSession(): void
    {
        $this->dbh->exec('INSERT INTO people (shortname, name, name_formal, date_created)
            VALUES ("zztestlegislator", "Legislator, Test", "Test Legislator", NOW())');
        $person_id = (int) $this->dbh->lastInsertId();
        $this->dbh->exec('INSERT INTO terms
                (person_id, name_formatted, lis_shortname, lis_id, chamber, district_id, date_started,
                 date_ended, partisanship, date_created, date_modified)
            VALUES (' . $person_id . ', "Sen. Test Legislator (D)", "test", ' . self::LIS_ID . ', "senate", 1,
                "2016-01-13", "2020-01-08", 0, NOW(), NOW())');

        $this->assertTrue($this->import()->add_legislator($this->legislator('2015-11-16')));

        $this->assertSame(self::SESSION_START, $this->newTermStart($person_id));
    }

    public function testANewLegislatorsTermStartsWhenLisSays(): void
    {
        $this->assertTrue($this->import()->add_legislator($this->legislator('2098-01-14')));

        $person_id = (int) $this->dbh->query('SELECT id FROM people WHERE shortname = "zztestlegislator"')
            ->fetchColumn();
        $this->assertSame('2098-01-14', $this->newTermStart($person_id));
    }

    public function testANewLegislatorsTermHasTheirLisShortname(): void
    {
        $legislator = $this->legislator('2098-01-14') + ['lis_shortname' => 'Legislator, T.'];
        $this->assertTrue($this->import()->add_legislator($legislator));

        $this->assertSame('legislator, t.', (string) $this->dbh->query('SELECT lis_shortname FROM terms
            WHERE lis_id = ' . self::LIS_ID . ' AND date_ended IS NULL')->fetchColumn());
    }

    /** @return array<string,mixed> */
    private function legislator(string $service_begin_date): array
    {
        return [
            'name_formal' => 'Test Legislator',
            'name' => 'Legislator, Test',
            'name_formatted' => 'Sen. Test Legislator (D)',
            'shortname' => 'zztestlegislator',
            'chamber' => 'senate',
            'district_id' => 1,
            'date_started' => $service_begin_date,
            'party' => 'D',
            'lis_id' => (string) self::LIS_ID,
            'email' => 'senatorlegislator@senate.virginia.gov',
        ];
    }

    private function newTermStart(int $person_id): string
    {
        return (string) $this->dbh->query('SELECT date_started FROM terms
            WHERE person_id = ' . $person_id . ' AND date_ended IS NULL')->fetchColumn();
    }

    private function import(): Import
    {
        return new class (new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        }) extends Import {
            protected function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2098-01-20');
            }

            protected function emailDomainExists($domain)
            {
                return true;
            }
        };
    }
}
