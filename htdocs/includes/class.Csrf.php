<?php

/**
 * Protection against cross-site request forgery: a form that changes something includes a token
 * kept in the visitor's session (Csrf::field()), and the page that receives it refuses the change
 * unless it's there (Csrf::valid()). A page on another site can make a visitor's browser post a
 * form here, with their session cookie, but it can't read the token.
 *
 * The token is per session, not per form, so a visitor can keep several pages open. It's sent as a
 * form field (csrf_token) or, by scripts, as an X-CSRF-Token header.
 */
class Csrf
{
    /**
     * The form field the token is sent in.
     */
    const FIELD = 'csrf_token';

    /**
     * This session's token, made if it hasn't been yet. Starts a session if there isn't one.
     */
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])
            || strlen($_SESSION['csrf_token']) != 64) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * A hidden form field holding the token.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . self::token() . '" />';
    }

    /**
     * Whether this request came with this session's token, in the form or in a header.
     */
    public static function valid(): bool
    {
        $sent = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        $token = $_SESSION['csrf_token'] ?? null;
        return is_string($sent) && is_string($token) && $token !== '' && hash_equals($token, $sent);
    }
}
