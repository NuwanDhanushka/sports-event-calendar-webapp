<?php
namespace App\Core;

/**
 * Logger class
 * Handles logging
 * Logs are written to a JSON file in the logs directory
 */

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
        /** if the directory is not provided, use the default */
        if ($directory === null) {
            /** get the absolute path to the storage directory which is 3 levels up from the dir */
            $directory = dirname(__DIR__, 3) . '/storage/logs';
        }

        /** remove trailing slash if present */
        self::$logDirectory = rtrim($directory, '/');

        /** create the directory if it doesn't exist */
        if (!is_dir(self::$logDirectory)) {
            if (!mkdir(self::$logDirectory, 0775, true) && !is_dir(self::$logDirectory)) {
                throw new \RuntimeException('Cannot create log directory: ' . self::$logDirectory);
            }
        }

        self::$isInitialized = true;
    }

   /********** LOG LEVELS **********/
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
        /** initialize logger if not already initialized */
        if (!self::$isInitialized) {
            self::init();
        }

        /** build log record */
        $logRecord = [
            'timestamp' => gmdate('c'),
            'level' => strtolower($level),
            'message' => $message,
        ];

        /** add context if provided */
        if (!empty($context)) {
            // Keep context as-is. keep it small and avoid secrets.
            $logRecord['context'] = $context;
        }

        /** encode log record to JSON */
        $jsonLine = json_encode($logRecord, JSON_UNESCAPED_SLASHES);
        if ($jsonLine === false) {
            // Skip if encoding fails
            return;
        }

        /** append to log record to the log file in logs directory with timestamp in filename*/
        $logFilePath = self::$logDirectory . '/app-' . gmdate('Y-m-d') . '.log';
        file_put_contents($logFilePath, $jsonLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

}