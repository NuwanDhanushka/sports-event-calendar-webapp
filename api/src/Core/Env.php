<?php
declare(strict_types=1);

namespace App\Core;

class Env
{
    private static array $vars = [];

    public static function load(string $file, bool $mirrorToEnv = true): void
    {
        if (!is_file($file)) return;

        $vals = parse_ini_file($file, false, INI_SCANNER_RAW) ?: [];
        foreach ($vals as $k => $v) {
            $v = (string)$v;
            self::$vars[$k] = $v;
            if ($mirrorToEnv) {
                $_ENV[$k] = $v;
            }
        }
    }

    /** Get raw string (or null/default). */
    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$vars[$key] ?? $_ENV[$key] ?? $default;
    }

    /** Required string (throws if missing/empty). */
    public static function required(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return $v;
    }

    /** Runtime override. */
    public static function set(string $key, string $value, bool $mirrorToEnv = true): void
    {
        self::$vars[$key] = $value;
        if ($mirrorToEnv) {
            $_ENV[$key] = $value;
        }
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return match (strtolower(trim($v))) {
            '1','true','yes','on'  => true,
            '0','false','no','off' => false,
            default => $default,
        };
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return ($v !== null && is_numeric($v)) ? (int)$v : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key);
        return ($v !== null && is_numeric($v)) ? (float)$v : $default;
    }
}
