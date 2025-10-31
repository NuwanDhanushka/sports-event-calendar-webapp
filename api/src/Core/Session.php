<?php
namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(array $opts = []): void
    {
        if (self::$started) return;
        if (session_status() === PHP_SESSION_ACTIVE) { self::$started = true; return; }

        $defaults = [
            'cookie_httponly' => true,
            'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => 1,
        ];
        session_start($opts + $defaults);
        self::$started = true;
    }

    public static function id(): string { return session_id(); }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        session_regenerate_id($deleteOld);
    }

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
