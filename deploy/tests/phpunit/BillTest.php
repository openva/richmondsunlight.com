<?php

use PHPUnit\Framework\TestCase;

class BillTest extends TestCase
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

        $bill = new Bill2();
        self::$id = $bill->getid(2025, 'hb41');

        if (self::$id !== false) {
            $bill->id = self::$id;
            self::$info = $bill->info();
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
        $this->assertNotFalse(self::$id, 'getid should return an ID for HB41 (2025)');
    }

    public function testGetidReturnsNumericId(): void
    {
        $this->assertIsNumeric(self::$id, 'getid should return a numeric ID');
    }

    public function testInfoBillNumber(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('hb41', self::$info['number']);
    }

    public function testInfoYear(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame(2025, (int) self::$info['year']);
    }

    public function testInfoChamber(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('house', self::$info['chamber']);
    }

    public function testInfoStatus(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame('failed committee', self::$info['status']);
    }

    public function testInfoCatchLine(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertStringContainsString('Standards of Learning', self::$info['catch_line']);
    }
}
