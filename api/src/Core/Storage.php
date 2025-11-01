<?php
namespace App\Core;

class Storage
{

    public static function basePath(): string
    {
        // api/src/Core -> go 3 levels up = project root
        return rtrim(dirname(__DIR__, 3) . '/storage', '/');
    }

    /** Absolute path helper for a storage-relative path */
    public static function absolutePath(string $relative): string
    {
        return self::basePath() . '/' . ltrim($relative, '/');
    }

    /** Ensure storage (or a subdirectory) exists and return its absolute path */
    public static function ensureDir(string $subdir = ''): string
    {
        $dir = $subdir ? (self::basePath() . '/' . ltrim($subdir, '/')) : self::basePath();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create storage dir: ' . $dir);
        }
        return $dir;
    }

    /** Generate a random file stem (extension added later from MIME) */
    public static function randomName(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Validate and persist an uploaded image into /storage/<subdir>/...
     * Returns a storage-relative path like: "events/2025/10/abcd1234.png"
     */
    public static function saveUploaded(array $file, string $subdir): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Invalid upload payload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error code: ' . $file['error']);
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            throw new \RuntimeException('File too large or empty (max 10MB).');
        }


        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($file['tmp_name']) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Unsupported file type.');
        }

        $dir = self::ensureDir(trim($subdir, '/'));
        $name = self::randomName() . '.' . $allowed[$mime];
        $destination = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return trim($subdir, '/') . '/' . $name; // storage-relative
    }

    /** Build a public URL under /storage (server should map /storage -> <project>/storage) */
    public static function url(string $relative): string
    {
        return '/storage/' . ltrim($relative, '/');
    }

    /** Delete a storage-relative file */
    public static function delete(string $relative): bool
    {
        $abs = self::absolutePath($relative);
        return is_file($abs) ? @unlink($abs) : false;
    }


}