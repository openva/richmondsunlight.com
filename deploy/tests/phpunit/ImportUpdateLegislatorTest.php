<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::update_legislator()'s handling of party and place, which the daily refresh provides.
 */
class ImportUpdateLegislatorTest extends TestCase
{
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

    public function testAChangeOfPartyIsStoredInNameFormattedToo(): void
    {
        $term = $this->insertTerm('D', 'Fairfax', 'Del. Jane Doe (D-Fairfax)');

        $this->assertTrue($this->import()->update_legislator(['id' => $term, 'party' => 'I']));

        $this->assertSame(
            ['party' => 'I', 'place' => 'Fairfax', 'name_formatted' => 'Del. Jane Doe (I-Fairfax)'],
            $this->stored($term)
        );
    }

    public function testAnUnknownPartyIsIgnored(): void
    {
        $term = $this->insertTerm('D', 'Fairfax', 'Del. Jane Doe (D-Fairfax)');

        $this->import()->update_legislator(['id' => $term, 'party' => 'X', 'email' => 'deljdoe@house.virginia.gov']);

        $this->assertSame('D', $this->stored($term)['party']);
    }

    /**
     * LIS's place is the town of a district or mailing address, often a P.O. box's: it doesn't
     * replace one that's stored.
     */
    public function testAStoredPlaceIsKept(): void
    {
        $term = $this->insertTerm('D', 'Fairfax', 'Del. Jane Doe (D-Fairfax)');

        $this->import()->update_legislator(['id' => $term, 'place' => 'Annandale', 'email' => 'deljdoe@house.virginia.gov']);

        $this->assertSame('Fairfax', $this->stored($term)['place']);
    }

    public function testAMissingPlaceIsFilledIn(): void
    {
        $term = $this->insertTerm('D', null, 'Del. Jane Doe (D)');

        $this->import()->update_legislator(['id' => $term, 'place' => 'Annandale']);

        $this->assertSame('Annandale', $this->stored($term)['place']);
    }

    private function import(): Import
    {
        return new class (new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        }) extends Import {
            protected function emailDomainExists($domain)
            {
                return true;
            }
        };
    }

    private function insertTerm(string $party, ?string $place, string $name_formatted): int
    {
        $this->dbh->prepare(
            'INSERT INTO terms
                (person_id, name_formatted, lis_shortname, lis_id, chamber, party, district_id,
                 date_started, place, partisanship, date_created, date_modified)
             VALUES (1, :name_formatted, "test", 997, "house", :party, 1, "2024-01-10", :place, 0,
                 NOW(), NOW())'
        )->execute([':name_formatted' => $name_formatted, ':party' => $party, ':place' => $place]);

        return (int) $this->dbh->lastInsertId();
    }

    /** @return array<string,mixed> */
    private function stored(int $term_id): array
    {
        $stmt = $this->dbh->prepare('SELECT party, place, name_formatted FROM terms WHERE id = :id');
        $stmt->execute([':id' => $term_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
