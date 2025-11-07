<?php
namespace App\Core;

/**
 * Session wrapper to mange sessions
 */
final class Session
{
    /** for tracking if session has been started */
    private static bool $started = false;

    /**
     * Start the session only once, applying secure defaults.
     * Can be override defaults via $opts
     */
    public static function start(array $opts = []): void
    {
        if (self::$started) return;
        if (session_status() === PHP_SESSION_ACTIVE) { self::$started = true; return; }

        /** set default session options */
        $defaults = [
            'cookie_httponly' => true,
            'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => 1,
        ];

        /** start the session */
        session_start($opts + $defaults);
        self::$started = true;
    }

    /**
     * Get the session ID
     */
    public static function id(): string { return session_id(); }

    /**
     * Get a key from the session if not found return default
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a key in the session
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a key exists in the session
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a key from the session
     * @param string $key
     * @return void
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Regenerate the session ID to prevent fixation.
     * $deleteOld is default true to delete the old session file.
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        session_regenerate_id($deleteOld);
    }

    /**
     * Destroy the session clear data, expire cookie, and end the session.
     * @return void
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$started = false;
    }
}
