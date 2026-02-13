<?php

use PHPUnit\Framework\TestCase;

class ResetPasswordTest extends TestCase
{
    private static bool $dbAvailable = false;
    private static string $htdocsRoot = '';
    private static string $autoloadStubPath = '';

    private bool $createdAutoloadStub = false;
    private ?string $originalPassword = null;
    private ?string $originalHash = null;

    private const USER_ID = 90001;
    private const SEED_HASH = 'testuser';
    private const NEW_PASSWORD = 'newpass123';

    public static function setUpBeforeClass(): void
    {
        self::$htdocsRoot = realpath(__DIR__ . '/../../../htdocs');
        // reset-password.php requires vendor/autoload.php relative to htdocs/,
        // which differs from the project's actual vendor dir (htdocs/includes/vendor/).
        self::$autoloadStubPath = self::$htdocsRoot . '/vendor/autoload.php';

        $database = new Database();
        if ($database->connect_mysqli() === false) {
            return;
        }
        self::$dbAvailable = true;
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }

        // Check test user exists before trying to save its state
        $result = mysqli_query($GLOBALS['db'], 'SELECT password, private_hash FROM users WHERE id=' . self::USER_ID);
        if ($result === false || mysqli_num_rows($result) === 0) {
            $this->markTestSkipped('Test user ' . self::USER_ID . ' missing from users table');
        }

        // Save original state so tearDown can restore it
        $row = mysqli_fetch_assoc($result);
        $this->originalPassword = $row['password'];
        $this->originalHash = $row['private_hash'];

        // Reset to known values for the test
        mysqli_query($GLOBALS['db'], 'UPDATE users SET password=MD5("password123"), private_hash="' . self::SEED_HASH . '" WHERE id=' . self::USER_ID);

        // Create stub autoload.php if reset-password.php will look for it there
        if (!file_exists(self::$autoloadStubPath)) {
            $vendorDir = dirname(self::$autoloadStubPath);
            if (!is_dir($vendorDir)) {
                mkdir($vendorDir, 0775, true);
            }
            file_put_contents(self::$autoloadStubPath, "<?php\n");
            $this->createdAutoloadStub = true;
        }
    }

    protected function tearDown(): void
    {
        // Restore user state
        if (self::$dbAvailable && $this->originalPassword !== null) {
            mysqli_query(
                $GLOBALS['db'],
                'UPDATE users SET password="' . $this->originalPassword . '", private_hash="' . $this->originalHash . '" WHERE id=' . self::USER_ID
            );
        }

        // Remove stub autoload if we created it
        if ($this->createdAutoloadStub && file_exists(self::$autoloadStubPath)) {
            unlink(self::$autoloadStubPath);
            $this->createdAutoloadStub = false;
        }
    }

    public function testResetPasswordUpdatesPassword(): void
    {
        $this->runResetPasswordPage();

        $result = mysqli_query($GLOBALS['db'], 'SELECT password FROM users WHERE id=' . self::USER_ID);
        $row = mysqli_fetch_assoc($result);
        $this->assertSame(md5(self::NEW_PASSWORD), $row['password'], 'Password should be updated by reset flow');
    }

    public function testResetPasswordRotatesPrivateHash(): void
    {
        $this->runResetPasswordPage();

        $result = mysqli_query($GLOBALS['db'], 'SELECT private_hash FROM users WHERE id=' . self::USER_ID);
        $row = mysqli_fetch_assoc($result);
        $this->assertNotSame(self::SEED_HASH, $row['private_hash'], 'private_hash should rotate after reset');
    }

    public function testResetPasswordGeneratesValidHashFormat(): void
    {
        $this->runResetPasswordPage();

        $result = mysqli_query($GLOBALS['db'], 'SELECT private_hash FROM users WHERE id=' . self::USER_ID);
        $row = mysqli_fetch_assoc($result);
        $this->assertMatchesRegularExpression(
            '/^[bcdfghjklmnpqrstvxyz0123456789]{8}$/',
            $row['private_hash'],
            'private_hash should be regenerated with expected format'
        );
    }

    private function runResetPasswordPage(): void
    {
        $originalDir = getcwd();
        chdir(self::$htdocsRoot);

        $_GET = [];
        $_POST = [
            'reset_password' => '1',
            'hash' => self::SEED_HASH,
            'password' => self::NEW_PASSWORD,
            'password_2' => self::NEW_PASSWORD,
        ];
        $_SERVER['SERVER_NAME'] ??= 'localhost';
        $_SERVER['HTTP_HOST'] ??= 'localhost';

        // Close any active session so reset-password.php can call session_start() cleanly
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Two levels of buffering: the page calls ob_end_flush() internally,
        // which would flush the inner buffer up to the next level. The outer
        // buffer catches anything the inner one flushes before we clean both.
        $levelBefore = ob_get_level();
        ob_start(); // outer — catches anything the inner buffer flushes
        ob_start(); // inner — the page writes here
        include self::$htdocsRoot . '/reset-password.php';
        while (ob_get_level() > $levelBefore) {
            ob_end_clean();
        }

        chdir($originalDir);
    }
}
