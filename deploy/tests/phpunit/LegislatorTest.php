<?php

use PHPUnit\Framework\TestCase;

class LegislatorTest extends TestCase
{
    private static bool $dbAvailable = false;
    private static mixed $id = false;
    private static mixed $info = null;

    public static function setUpBeforeClass(): void
    {
        $database = new Database();
        if ($database->connect_mysqli() === false) {
            return;
        }
        self::$dbAvailable = true;

        $legislator = new Legislator();
        self::$id = $legislator->getid('rcdeeds');

        if (self::$id !== false) {
            self::$info = $legislator->info(self::$id);
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
    }

    public function testGetidReturnsAnId(): void
    {
        $this->assertNotFalse(self::$id, 'getid should return an ID for rcdeeds');
    }

    public function testGetidReturns269(): void
    {
        $this->assertEquals('269', self::$id, 'getid should return ID 269');
    }

    public function testInfoShortname(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('rcdeeds', self::$info['shortname']);
    }

    public function testInfoName(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('Creigh Deeds', self::$info['name']);
    }

    public function testInfoChamber(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('senate', self::$info['chamber']);
    }

    public function testInfoDistrict(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame(11, (int) self::$info['district']);
    }

    public function testInfoParty(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('D', self::$info['party']);
    }

    public function testInfoPartyName(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('Democratic', self::$info['party_name']);
    }

    public function testInfoPrefix(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('Sen.', self::$info['prefix']);
    }

    public function testInfoSuffix(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('(D-Charlottesville)', self::$info['suffix']);
    }

    public function testInfoEmail(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('senatordeeds@senate.virginia.gov', self::$info['email']);
    }

    public function testInfoLisId(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('S62', self::$info['lis_id']);
    }

    public function testInfoWebsiteName(): void
    {
        if (self::$info === null || self::$info === false) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('senatordeeds.com', self::$info['website_name']);
    }

    /**
     * A person with no term isn't a legislator, so isn't a current one.
     */
    public function testCurrentListExcludesPeopleWithoutTerms(): void
    {
        mysqli_query(
            $GLOBALS['db'],
            'INSERT INTO people (id, shortname, name, name_formal, date_created)
            VALUES (65000, "zztestnoterm", "Noterm, Test", "Test Noterm", NOW())'
        );

        try {
            $legislator = new Legislator();
            $shortnames = array_column($legislator->get_list('current'), 'shortname');
        } finally {
            mysqli_query($GLOBALS['db'], 'DELETE FROM people WHERE id = 65000');
        }

        $this->assertContains('rcdeeds', $shortnames);
        $this->assertNotContains('zztestnoterm', $shortnames);
    }

    public function testStatisticsCountBillsCopatronsAndTopics(): void
    {
        if (self::$id === false) {
            $this->markTestSkipped('getid returned false');
        }

        $statistics = Legislator::statistics((int) self::$id, SESSION_YEAR, true);

        $this->assertSame(['batting', 'copatroned', 'copatrons', 'pool', 'tags'], array_keys($statistics));
        $this->assertLessThanOrEqual($statistics['batting']['total'], $statistics['batting']['passed']);
        foreach (['copatroned', 'copatrons', 'pool', 'tags'] as $counts) {
            $this->assertContainsOnlyInt($statistics[$counts]);
        }
        $tag_counts = array_values($statistics['tags']);
        $sorted = $tag_counts;
        rsort($sorted);
        $this->assertSame($sorted, $tag_counts, 'Tags should be listed most-used first');
    }

    /**
     * The second time, the statistics come from Memcached, and must be the same.
     */
    public function testCachedStatisticsAreTheSame(): void
    {
        if (self::$id === false) {
            $this->markTestSkipped('getid returned false');
        }
        if (MEMCACHED_SERVER == '' || !class_exists('Memcached')) {
            $this->markTestSkipped('Memcached not available');
        }

        $mc = new Memcached();
        $mc->addServer(MEMCACHED_SERVER, MEMCACHED_PORT);
        $mc->delete('legislator-statistics-' . self::$id . '-' . SESSION_YEAR);

        $first = Legislator::statistics((int) self::$id, SESSION_YEAR, true);
        $this->assertSame($first, $mc->get('legislator-statistics-' . self::$id . '-' . SESSION_YEAR));
        $this->assertSame($first, Legislator::statistics((int) self::$id, SESSION_YEAR, true));
    }
}
