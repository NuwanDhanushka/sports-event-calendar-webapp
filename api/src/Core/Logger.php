<?php
namespace App\Core;

final class Logger
{
    private static bool $isInitialized = false;
    private static string $logDirectory;

    /**
     * Initialize once (optional; auto-inits on first log).
     * @param string|null $directory Absolute path for log files (defaults to <project>/storage/logs)
     */
    public static function init(?string $directory = null): void
    {
        if ($directory === null) {
            $directory = dirname(__DIR__, 3) . '/storage/logs';
        }

        self::$logDirectory = rtrim($directory, '/');

        if (!is_dir(self::$logDirectory)) {
            if (!mkdir(self::$logDirectory, 0775, true) && !is_dir(self::$logDirectory)) {
                throw new \RuntimeException('Cannot create log directory: ' . self::$logDirectory);
            }
        }

        self::$isInitialized = true;
    }

    // Convenience methods (no filtering — everything is logged)
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Write a single JSON log line (no level threshold).
     * Example: Logger::log('info', 'Created event', ['event_id'=>5]);
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$isInitialized) {
            self::init();
        }

        $logRecord = [
            'timestamp' => gmdate('c'),
            'level' => strtolower($level),
            'message' => $message,
        ];
        if (!empty($context)) {
            // Keep context as-is; keep it small and avoid secrets.
            $logRecord['context'] = $context;
        }

        $jsonLine = json_encode($logRecord, JSON_UNESCAPED_SLASHES);
        if ($jsonLine === false) {
            // Skip if encoding fails (keep implementation minimal)
            return;
        }

        $logFilePath = self::$logDirectory . '/app-' . gmdate('Y-m-d') . '.log';
        file_put_contents($logFilePath, $jsonLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

}