<?php

use PHPUnit\Framework\TestCase;

/**
 * Password, which hashes passwords and checks them against every kind of hash that's been stored.
 */
class PasswordTest extends TestCase
{
    /**
     * Has every character that escaping or URL-decoding changes.
     */
    private const TRICKY = "it's \"a\" back\\slash +plus %41 \x1a";

    public function testAPasswordMatchesItsHash(): void
    {
        $hash = Password::hash(self::TRICKY);

        $this->assertTrue(Password::verify(self::TRICKY, $hash));
        $this->assertFalse(Password::verify('something else', $hash));
        $this->assertFalse(Password::needs_upgrade($hash));
    }

    public function testEachHashIsSalted(): void
    {
        $this->assertNotSame(Password::hash('password123'), Password::hash('password123'));
    }

    public function testNoHashMatchesNothing(): void
    {
        $this->assertFalse(Password::verify('password123', null));
        $this->assertFalse(Password::verify('password123', ''));
    }

    /**
     * The three ways that MD5 hashes were made.
     */
    public static function legacyHashes(): array
    {
        $escaped = Password::escape(self::TRICKY);

        return [
            'as typed' => [md5(self::TRICKY)],
            'escaped' => [md5($escaped)],
            'escaped and URL-decoded' => [md5(urldecode($escaped))],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legacyHashes')]
    public function testAnMd5HashStillWorksButNeedsUpgrading(string $md5): void
    {
        $this->assertTrue(Password::verify(self::TRICKY, $md5));
        $this->assertTrue(Password::verify(self::TRICKY, strtoupper($md5)));
        $this->assertFalse(Password::verify('something else', $md5));
        $this->assertTrue(Password::needs_upgrade($md5));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legacyHashes')]
    public function testAWrappedMd5HashStillWorksButNeedsUpgrading(string $md5): void
    {
        $wrapped = Password::wrap($md5);

        $this->assertStringStartsWith(Password::LEGACY_PREFIX, $wrapped);
        $this->assertStringNotContainsString($md5, $wrapped);
        $this->assertTrue(Password::verify(self::TRICKY, $wrapped));
        $this->assertFalse(Password::verify('something else', $wrapped));
        $this->assertTrue(Password::needs_upgrade($wrapped));
    }

    /**
     * A wrapped hash fits in users.password.
     */
    public function testAWrappedHashFitsTheColumn(): void
    {
        $this->assertLessThanOrEqual(255, strlen(Password::wrap(md5('password123'))));
    }

    /**
     * The old hashes were of mysqli_real_escape_string()'s output, so escape() has to match it.
     */
    public function testEscapingMatchesMysqli(): void
    {
        $db = (new Database())->connect_mysqli();
        if ($db === false) {
            $this->markTestSkipped('Database not available');
        }

        foreach ([self::TRICKY, "null\0byte", "line\nbreak\r\n", 'plain', 'Ünïcödé ’quotes’'] as $string) {
            $this->assertSame(mysqli_real_escape_string($db, $string), Password::escape($string));
        }
    }
}
