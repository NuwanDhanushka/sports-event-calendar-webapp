<?php

namespace App\Core;

class Storage
{

    public static function basePath(): string
    {
        $projectRoot = dirname(__DIR__, 3);
        $path = Env::get('STORAGE_PATH', $projectRoot . '/storage');
        return rtrim($path, '/');
    }

    public static function ensureDir(string $sub = ''): string
    {

        $dir = $sub ? (self::basePath() . '/' . ltrim($sub, '/')) : self::basePath();

        try {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create storage dir: $dir");
            }
        } catch (\Exception $e) {
            throw new \Exception("Failed to create storage dir: $e");
        }

        return $dir;
    }

    public static function randomName(): string
    {
        return bin2hex(random_bytes(8));
    }

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

    public static function url(string $relative): string
    {
        return '/storage/' . ltrim($relative, '/');
    }


    public static function delete(string $relative): bool
    {
        $abs = self::basePath() . '/' . ltrim($relative, '/');
        return is_file($abs) ? @unlink($abs) : false;
    }


}