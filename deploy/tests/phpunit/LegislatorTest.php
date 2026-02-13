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
}
