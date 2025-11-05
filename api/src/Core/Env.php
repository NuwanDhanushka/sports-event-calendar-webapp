<?php
declare(strict_types=1);

namespace App\Core;
/**
 * Environment variables class
 * Provides a simple interface to access environment variables.
 */
class Env
{
    private static array $vars = [];

    /**
     * Load environment variables from a file.
     * @param string $file
     * @param bool $mirrorToEnv
     * @return void
     */
    public static function load(string $file, bool $mirrorToEnv = true): void
    {
        if (!is_file($file)) return;

        /** get the values from the file by parase_ini_file function */
        $vals = parse_ini_file($file, false, INI_SCANNER_RAW) ?: [];

        /** loop through the values and set them to the class vars and $_ENV */
        foreach ($vals as $k => $v) {
            $v = (string)$v; // normalize to string
            self::$vars[$k] = $v;
            if ($mirrorToEnv) {
                $_ENV[$k] = $v;
            }
        }
    }


    /**
     * Get a raw string value from the environment.
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$vars[$key] ?? $_ENV[$key] ?? $default;
    }

    /**
     * Get a required string value from the environment.
     * throw exception if missing/empty.
     * @param string $key
     * @return string
     */
    public static function required(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return $v;
    }

    /**
     * Set a value in the environment on runtime.
     * @param string $key
     * @param string $value
     * @param bool $mirrorToEnv
     * @return void
     */
    public static function set(string $key, string $value, bool $mirrorToEnv = true): void
    {
        self::$vars[$key] = $value;
        if ($mirrorToEnv) {
            $_ENV[$key] = $value;
        }
    }

    /**
     * Get a boolean value from the environment.
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        /** match the value to the boolean values and return the boolean result */
        return match (strtolower(trim($v))) {
            '1','true','yes','on'  => true,
            '0','false','no','off' => false,
            default => $default,
        };
    }

    /**
     * Get an integer value from the environment.
     * @param string $key
     * @param int $default
     * @return int
     */
    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        /** check if the value is numeric and not null and parse it to int */
        return ($v !== null && is_numeric($v)) ? (int)$v : $default;
    }

    /**
     * Get a float value from the environment.
     * @param string $key
     * @param float $default
     * @return float
     */
    public static function float(string $key, float $default = 0.0): float
    {
        /** get the value from the environment and check its numeric and not null and parse it to float */
        $value = self::get($key);
        return ($value !== null && is_numeric($value)) ? (float)$value : $default;
    }
}
