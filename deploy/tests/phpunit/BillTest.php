<?php

use PHPUnit\Framework\TestCase;

class BillTest extends TestCase
{
    private static bool $dbAvailable = false;
    private static mixed $id = false;
    private static mixed $info = null;
    private static string $billNumber = '';
    private static int $billYear = 0;

    public static function setUpBeforeClass(): void
    {
        $database = new Database();
        if ($database->connect_mysqli() === false) {
            return;
        }
        self::$dbAvailable = true;

        // Find any bill in the seeded database along with its session year.
        $result = mysqli_query(
            $GLOBALS['db'],
            'SELECT bills.number, sessions.year
             FROM bills
             JOIN sessions ON bills.session_id = sessions.id
             LIMIT 1'
        );
        if (!$result || mysqli_num_rows($result) === 0) {
            return;
        }
        $row = mysqli_fetch_assoc($result);
        self::$billNumber = $row['number'];
        self::$billYear   = (int) $row['year'];

        $bill = new Bill2();
        self::$id = $bill->getid(self::$billYear, self::$billNumber);

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
        if (self::$billNumber === '') {
            $this->markTestSkipped('No bills found in test database');
        }
    }

    public function testGetidReturnsAnId(): void
    {
        $this->assertNotFalse(self::$id, 'getid should return an ID for ' . strtoupper(self::$billNumber) . ' (' . self::$billYear . ')');
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
        $this->assertSame(self::$billNumber, self::$info['number']);
    }

    public function testInfoYear(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertSame(self::$billYear, (int) self::$info['year']);
    }

    public function testInfoChamber(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertContains(self::$info['chamber'], ['house', 'senate'], 'chamber should be house or senate');
    }

    public function testInfoStatus(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertIsString(self::$info['status'], 'status should be a string');
        $this->assertNotEmpty(self::$info['status'], 'status should not be empty');
    }

    public function testInfoCatchLine(): void
    {
        if (self::$info === null) {
            $this->markTestSkipped('getid returned false');
        }
        $this->assertIsString(self::$info['catch_line'], 'catch_line should be a string');
        $this->assertNotEmpty(self::$info['catch_line'], 'catch_line should not be empty');
    }
}
