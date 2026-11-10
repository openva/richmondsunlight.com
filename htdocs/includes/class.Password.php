<?php

/**
 * Hashing and checking users' passwords.
 *
 * Passwords are hashed with password_hash(), which salts each one and stores the salt, the
 * algorithm, and its cost in the hash itself, so users.password is all that's needed.
 *
 * Accounts made before that have unsalted MD5 hashes, which are still accepted, and replaced with
 * a new hash when the person next logs in. To protect the accounts of people who don't log in,
 * deploy/migrate-password-hashes.php replaces each remaining MD5 hash with password_hash() of that
 * MD5 hash, marked with LEGACY_PREFIX. Those are accepted, and replaced on login, in the same way.
 */
class Password
{
    /**
     * Marks an old MD5 hash that has been hashed again with password_hash().
     */
    public const LEGACY_PREFIX = 'md5:';

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Whether a password matches a stored hash, of any kind.
     */
    public static function verify(string $password, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if (str_starts_with($stored, self::LEGACY_PREFIX)) {
            $wrapped = substr($stored, strlen(self::LEGACY_PREFIX));
            foreach (self::legacy_hashes($password) as $md5) {
                if (password_verify($md5, $wrapped)) {
                    return true;
                }
            }
            return false;
        }

        if (self::is_md5($stored)) {
            foreach (self::legacy_hashes($password) as $md5) {
                if (hash_equals(strtolower($stored), $md5)) {
                    return true;
                }
            }
            return false;
        }

        return password_verify($password, $stored);
    }

    /**
     * Whether a stored hash should be replaced with a new one (when we have the password, at login).
     */
    public static function needs_upgrade(string $stored): bool
    {
        return str_starts_with($stored, self::LEGACY_PREFIX)
            || self::is_md5($stored)
            || password_needs_rehash($stored, PASSWORD_DEFAULT);
    }

    /**
     * Hash an old MD5 hash again, for accounts whose passwords we don't have.
     */
    public static function wrap(string $md5): string
    {
        return self::LEGACY_PREFIX . password_hash(strtolower($md5), PASSWORD_DEFAULT);
    }

    public static function is_md5(string $stored): bool
    {
        return preg_match('/^[0-9a-f]{32}$/i', $stored) === 1;
    }

    /**
     * The MD5 hashes that a password may have been stored as. Over the years, it was hashed as
     * typed (when resetting a password); after being escaped for SQL (when logging in, or changing
     * it in account settings or Photosynthesis); and after being escaped and then URL-decoded (when
     * registering).
     */
    private static function legacy_hashes(string $password): array
    {
        $escaped = self::escape($password);

        return array_unique([
            md5($password),
            md5($escaped),
            md5(urldecode($escaped)),
        ]);
    }

    /**
     * What mysqli_real_escape_string() did to a password, with the site's UTF-8 connection. (Done
     * here, rather than by calling it, so that checking a password needs no database connection.)
     */
    public static function escape(string $string): string
    {
        return strtr($string, [
            "\\" => "\\\\",
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "'" => "\\'",
            '"' => '\\"',
            "\x1a" => "\\Z",
        ]);
    }
}
