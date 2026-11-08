<?php

use PHPUnit\Framework\TestCase;

/**
 * Legislators' LIS shortnames (terms.lis_shortname), which are LIS's PatronDisplayName: the name
 * it uses for a member in vote tallies, like "Cole" or, while there are two Scotts, "Scott, D.".
 */
class ImportLisShortnameTest extends TestCase
{
    /** LIS numbers that no real legislator has. */
    private const HOUSE_LIS_ID = 996;
    private const SENATE_LIS_ID = 995;

    /** A delegate, as LIS's member API gives them. */
    private const MEMBER = [
        'MemberNumber' => 'H0996',
        'ChamberCode' => 'H',
        'MemberDisplayName' => 'Donald L. Scott, Jr.',
        'ListDisplayName' => 'Scott, Donald L.',
        'PatronDisplayName' => 'Scott, D.',
        'PartyCode' => 'D',
        'DistrictName' => '88th',
    ];

    private ?PDO $dbh = null;

    protected function tearDown(): void
    {
        if ($this->dbh !== null && $this->dbh->inTransaction()) {
            $this->dbh->rollBack();
        }
    }

    public function testTheApiRecordHasTheLisShortname(): void
    {
        $data = $this->import([self::MEMBER])->fetch_legislator_data_api('house', 'H0996');

        $this->assertSame('scott, d.', $data['lis_shortname']);
    }

    public function testTheRosterHasTheLisShortname(): void
    {
        $roster = $this->import([self::MEMBER])->fetch_active_members('house');

        $this->assertSame('scott, d.', $roster[0]['lis_shortname']);
    }

    /**
     * Without a PatronDisplayName there's no LIS shortname, rather than a blank one, so that a
     * refresh doesn't erase the one that's stored.
     */
    public function testAMissingPatronDisplayNameGivesNoLisShortname(): void
    {
        $member = self::MEMBER;
        unset($member['PatronDisplayName']);
        $data = $this->import([$member])->fetch_legislator_data_api('house', 'H0996');

        $this->assertArrayNotHasKey('lis_shortname', $data);
    }

    public function testARefreshStoresTheLisShortname(): void
    {
        $this->connect();
        $term = $this->insertTerm('house', self::HOUSE_LIS_ID, 'scott, d.l.');

        $this->import()->update_legislator(['id' => $term, 'lis_shortname' => 'Scott, D.']);

        $this->assertSame('scott, d.', $this->storedLisShortname($term));
    }

    public function testARefreshWithoutALisShortnameKeepsTheStoredOne(): void
    {
        $this->connect();
        $term = $this->insertTerm('house', self::HOUSE_LIS_ID, 'scott, d.');

        $this->import()->update_legislator(['id' => $term, 'lis_shortname' => ' ', 'place' => 'Portsmouth']);

        $this->assertSame('scott, d.', $this->storedLisShortname($term));
    }

    /**
     * The roster's shortnames replace those of open terms that differ, matching House members by
     * "H0996" and senators by "995", as fetch_active_members() gives them.
     */
    public function testTheRosterUpdatesOpenTermsThatDiffer(): void
    {
        $this->connect();
        $delegate = $this->insertTerm('house', self::HOUSE_LIS_ID, 'scott, d.l.');
        $senator = $this->insertTerm('senate', self::SENATE_LIS_ID, 'pekarksky');
        $unchanged = $this->insertTerm('senate', self::SENATE_LIS_ID - 1, 'cole');

        $changed = $this->import()->update_lis_shortnames([
            ['chamber' => 'house', 'lis_id' => 'H0' . self::HOUSE_LIS_ID, 'lis_shortname' => 'scott, d.'],
            ['chamber' => 'senate', 'lis_id' => (string) self::SENATE_LIS_ID, 'lis_shortname' => 'pekarsky'],
            ['chamber' => 'senate', 'lis_id' => (string) (self::SENATE_LIS_ID - 1), 'lis_shortname' => 'cole'],
        ]);

        $this->assertSame(2, $changed);
        $this->assertSame('scott, d.', $this->storedLisShortname($delegate));
        $this->assertSame('pekarsky', $this->storedLisShortname($senator));
        $this->assertSame('cole', $this->storedLisShortname($unchanged));
    }

    /**
     * A past term keeps the name LIS used for the member then, and the other chamber's member
     * with the same number isn't theirs.
     */
    public function testTheRosterLeavesEndedTermsAndTheOtherChamberAlone(): void
    {
        $this->connect();
        $ended = $this->insertTerm('house', self::HOUSE_LIS_ID, 'scott, d.l.', '2020-01-08');
        $senator = $this->insertTerm('senate', self::HOUSE_LIS_ID, 'pekarsky');

        $changed = $this->import()->update_lis_shortnames([
            ['chamber' => 'house', 'lis_id' => 'H0' . self::HOUSE_LIS_ID, 'lis_shortname' => 'scott, d.'],
        ]);

        $this->assertSame(0, $changed);
        $this->assertSame('scott, d.l.', $this->storedLisShortname($ended));
        $this->assertSame('pekarsky', $this->storedLisShortname($senator));
    }

    public function testTheRosterSkipsMembersWithoutALisShortname(): void
    {
        $this->connect();
        $term = $this->insertTerm('house', self::HOUSE_LIS_ID, 'scott, d.');

        $changed = $this->import()->update_lis_shortnames([
            ['chamber' => 'house', 'lis_id' => 'H0' . self::HOUSE_LIS_ID],
            ['chamber' => 'house', 'lis_id' => 'H0' . self::HOUSE_LIS_ID, 'lis_shortname' => ''],
        ]);

        $this->assertSame(0, $changed);
        $this->assertSame('scott, d.', $this->storedLisShortname($term));
    }

    /**
     * @param array<int,array<string,mixed>> $members What LIS's member API returns.
     */
    private function import(array $members = []): Import
    {
        return new class (new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        }, $members) extends Import {
            public function __construct(Log $log, private array $members)
            {
                parent::__construct($log);
            }

            protected function http_get($url)
            {
                return false;
            }

            protected function lis_api_request($path, array $query = [])
            {
                if ($path === '/Member/api/getmembersasync' || $path === '/Member/api/getactivemembersasync') {
                    return ['Members' => $this->members];
                }
                return [];
            }

            protected function emailDomainExists($domain)
            {
                return true;
            }
        };
    }

    private function connect(): void
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

    private function insertTerm(string $chamber, int $lis_id, string $lis_shortname, ?string $date_ended = null): int
    {
        $this->dbh->prepare(
            'INSERT INTO terms
                (person_id, name_formatted, lis_shortname, lis_id, chamber, party, district_id,
                 date_started, date_ended, partisanship, date_created, date_modified)
             VALUES (1, "Del. Test Legislator (D)", :lis_shortname, :lis_id, :chamber, "D", 1,
                 "2016-01-13", :date_ended, 0, NOW(), NOW())'
        )->execute([
            ':lis_shortname' => $lis_shortname,
            ':lis_id' => $lis_id,
            ':chamber' => $chamber,
            ':date_ended' => $date_ended,
        ]);

        return (int) $this->dbh->lastInsertId();
    }

    private function storedLisShortname(int $term_id): string
    {
        $stmt = $this->dbh->prepare('SELECT lis_shortname FROM terms WHERE id = :id');
        $stmt->execute([':id' => $term_id]);

        return (string) $stmt->fetchColumn();
    }
}
