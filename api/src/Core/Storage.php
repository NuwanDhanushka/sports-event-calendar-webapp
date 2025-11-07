<?php
namespace App\Core;

class Storage
{

    /**
     * Base path for storage
     * @return string
     */
    public static function basePath(): string
    {
        /** return the absolute path to the storage directory
         * relative to the project root (one level up from the api folder)
         * api/src/Core -> go 3 levels up = project root
         */
        return rtrim(dirname(__DIR__, 3) . '/storage', '/');
    }

    /**
     * Absolute path helper for a storage-relative path
     * @param string $relative
     * @return string
     */
    public static function absolutePath(string $relative): string
    {
        /** trim leading slash and return the absolute path */
        return self::basePath() . '/' . ltrim($relative, '/');
    }

    /**
     * Ensure storage (or a subdirectory) exists and return its absolute path
     * @param string $subdir
     * @return string
     */
    public static function ensureDir(string $subdir = ''): string
    {
        /** trim leading slash and return the absolute path */
        $dir = $subdir ? (self::basePath() . '/' . ltrim($subdir, '/')) : self::basePath();

        /** create the directory if it doesn't exist */
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create storage dir: ' . $dir);
        }

        /** return the absolute path */
        return $dir;
    }

    /**
     * Generate a random file stem (extension added later from MIME)
     * @return string
     * @throws \Random\RandomException
     */
    public static function randomName(): string
    {
        /** generate a random 8-character hex string */
        return bin2hex(random_bytes(8));
    }

    /**
     * Validate and persist an uploaded image into /storage/<subdir>/...
     * Returns a storage-relative path like: "events/2025/10/abcd1234.png"
     * @param array $file
     * @param string $subdir
     * @return string
     * @throws \Random\RandomException
     */
    public static function saveUploaded(array $file, string $subdir): string
    {
        /** check if the file is valid and no errors */
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Invalid upload payload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error code: ' . $file['error']);
        }

        /** check if the file is too large or empty */
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            throw new \RuntimeException('File too large or empty (max 10MB).');
        }

        /** check if the file is an image */
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($file['tmp_name']) ?: '';

        /** check the file type is allowed */
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Unsupported file type.');
        }

        /** check if the directory exists, create it if not, and generate the path and name of the file */
        $dir = self::ensureDir(trim($subdir, '/'));
        $name = self::randomName() . '.' . $allowed[$mime];
        $destination = $dir . '/' . $name;

        /** move the file to the destination */
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return trim($subdir, '/') . '/' . $name; // storage relative
    }


    /**
     * Generate a public URL for a storage-relative path
     * public URL under /storage (server should map /storage -> <project>/storage)
     * @param string $relative
     * @return string
     */
    public static function url(string $relative): string
    {
        return '/storage/' . ltrim($relative, '/');
    }

    /**
     * Delete a file from storage
     * @param string $relative
     * @return bool
     */
    public static function delete(string $relative): bool
    {
        /** get the absolute path */
        $abs = self::absolutePath($relative);
        /** delete the file */
        return is_file($abs) ? @unlink($abs) : false;
    }


}