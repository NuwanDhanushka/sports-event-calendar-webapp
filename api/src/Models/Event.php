<?php

namespace App\Models;

use App\Core\Database;

class Event
{
    private int $id = 0;
    private ?string $title = null;
    private ?string $bannerPath = null;

    public function __construct(array $data = [])
    {
        if (array_key_exists('id', $data)) $this->id = (int)$data['id'];
        if (array_key_exists('title', $data)) $this->title = (string)$data['title'];
        if (array_key_exists('bannerPath', $data)) $this->bannerPath = (string)$data['bannerPath'];
    }

    /**
     * Return an event object from a database row
     * @param array $data
     * @return self
     */
    public static function fromRow(array $data): self
    {
        return new self([
            'id' => (int)$data['id'],
            'title' => (string)$data['title'],
            'bannerPath' => (string)$data['banner_path'],
        ]);
    }

    /**
     * Create a new event
     * @param array $data
     * @return int
     */
    public static function create(array $data, ?string $bannerPath = null): int
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title is required');
        }

        $database = new Database();

        if (!empty($bannerPath)) {
            $database->query('INSERT INTO events (title, banner_path) VALUES (:title, :banner_path)');
            $database->bind(':banner_path', $bannerPath);
        } else {
            $database->query('INSERT INTO events (title) VALUES (:title)');
        }

        $database->bind(':title', $title);
        $database->execute();

        return $database->lastId();
    }

    /** Find by ID */
    public static function find(int $id): ?self
    {
        $db = new Database();
        $row = $db->query('SELECT * FROM events WHERE id = :id')
            ->bind(':id', $id)
            ->single();

        return $row ? self::fromRow($row) : null;
    }

    /** List with pagination */
    public static function list(int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $db = new Database();
        $total = (int)$db->query('SELECT COUNT(*) FROM events')->value();

        // Using int-cast interpolation for LIMIT/OFFSET (compatible with native preparing)
        $rows = $db->query("SELECT * FROM events ORDER BY id DESC LIMIT :limit OFFSET :offset")
            ->bind(':limit', $limit)
            ->bind(':offset', $offset)
            ->results();

        return ['data' => $rows, 'total' => $total];
    }

    /** Update title; returns affected rows */
    public static function update(int $id, array $payload): int
    {
        if (!array_key_exists('title', $payload)) return 0;

        $title = trim((string)$payload['title']);

        $db = new Database();
        $db->query('UPDATE events SET title = :title WHERE id = :id');
        $db->bind(':title', $title);
        $db->bind(':id', $id);
        $db->execute();
        return $db->rowCount();
    }

    /** Delete; returns affected rows */
    public static function delete(int $id): int
    {
        $db = new Database();

        $db->query('DELETE FROM events WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();
        return $db->rowCount();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getBannerPath(): ?string
    {
        return $this->bannerPath;
    }

    public function setBannerPath(?string $bannerPath): void
    {
        $this->bannerPath = $bannerPath;
    }

    public static function setBanner(int $id, ?string $relativePath): int
    {
        $database = new Database();
        $database->query('UPDATE events SET banner_path = :path WHERE id = :id');
        $database->bind(':path', $relativePath);
        $database->bind(':id', $id);
        $database->execute();
        return $database->rowCount();
    }

    public static function getBannerPathById(int $id): ?string
    {
        $database = new Database();
        $database->query('SELECT banner_path FROM events WHERE id = :id');
        $database->bind(':id', $id);
        $row = $database->single();
        return $row ? ($row['banner_path'] ?? null) : null;
    }

    //add validation

}
