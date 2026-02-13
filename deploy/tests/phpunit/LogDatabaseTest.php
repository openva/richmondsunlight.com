<?php

use PHPUnit\Framework\TestCase;

class LogDatabaseTest extends TestCase
{
    private static bool $dbAvailable = false;
    private static ?PDO $pdo = null;

    private static bool $tableAvailable = false;

    public static function setUpBeforeClass(): void
    {
        try {
            $database = new Database();
            $pdo = $database->connect();
            if ($pdo === false) {
                return;
            }
            self::$dbAvailable = true;
            self::$pdo = $pdo;
            $stmt = $pdo->query("SHOW TABLES LIKE 'logs'");
            self::$tableAvailable = ($stmt->rowCount() > 0);
        } catch (Exception $e) {
            // DB not available; tests will be skipped via setUp()
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
        if (!self::$tableAvailable) {
            $this->markTestSkipped('logs table does not exist in this database');
        }
        self::$pdo->exec("DELETE FROM logs WHERE message LIKE 'TEST_LOG_%'");
    }

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            self::$pdo->exec("DELETE FROM logs WHERE message LIKE 'TEST_LOG_%'");
        }
    }

    public function testDatabaseReturnsTrueOnSuccess(): void
    {
        $log = new Log();
        $this->assertTrue($log->database('TEST_LOG_basic', 3));
    }

    public function testDatabaseInsertsRow(): void
    {
        $log = new Log();
        $log->database('TEST_LOG_basic', 3);

        $stmt = self::$pdo->prepare("SELECT message, level FROM logs WHERE message = :msg");
        $stmt->execute([':msg' => 'TEST_LOG_basic']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Row should exist in logs table after database()');
        $this->assertSame('TEST_LOG_basic', $row['message']);
        $this->assertSame(3, (int) $row['level']);
    }

    /**
     * @dataProvider severityLevelProvider
     */
    public function testDatabaseStoresCorrectSeverityLevel(int $level): void
    {
        $log = new Log();
        $msg = 'TEST_LOG_level_' . $level;
        $log->database($msg, $level);

        $stmt = self::$pdo->prepare("SELECT level FROM logs WHERE message = :msg");
        $stmt->execute([':msg' => $msg]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "Row should exist for level {$level}");
        $this->assertSame($level, (int) $row['level']);
    }

    public static function severityLevelProvider(): array
    {
        return [[1], [5], [8]];
    }

    public function testPutStoresMessagesInDatabase(): void
    {
        $log = new Log();
        $log->verbosity = 8;  // Only emergencies go to Slack
        $log->output = 'none'; // Avoid side effects
        $log->put('TEST_LOG_via_put', 1); // Level 1 = debug, well below verbosity

        $stmt = self::$pdo->prepare("SELECT message FROM logs WHERE message = :msg");
        $stmt->execute([':msg' => 'TEST_LOG_via_put']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'put() should store messages in the database even when below verbosity threshold');
    }

    public function testDatabaseReturnsFalseWhenNoConnection(): void
    {
        $log = new Log();
        $saved = $GLOBALS['db_pdo'];
        unset($GLOBALS['db_pdo']);
        try {
            $result = $log->database('TEST_LOG_nodb', 3);
        } finally {
            $GLOBALS['db_pdo'] = $saved;
        }
        $this->assertFalse($result);
    }
}
