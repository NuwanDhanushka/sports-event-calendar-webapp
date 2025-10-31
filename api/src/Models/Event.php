<?php
namespace App\Models;

use App\Core\Database;

class Event
{
    private int $id;
    private string $title;

    public function __construct(array $data = []) {
        if (array_key_exists('id', $data))    $this->id = (int)$data['id'];
        if (array_key_exists('title', $data)) $this->title = (string)$data['title'];
    }

    /**
     * Return an event object from a database row
     * @param array $data
     * @return self
     */
    public static function fromRow(array $data): self
    {
        return new self([
            'id'    => (int)$data['id'],
            'title' => (string)$data['title'],
        ]);
    }

    /**
     * Create a new event
     * @param array $data
     * @return int
     */
    public static function create(array $data): int
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title is required');
        }

        $db = new Database();
        $db->query('INSERT INTO events (title) VALUES (:title)')
            ->bind(':title', $title)
            ->execute();

        return $db->lastId();
    }

    /** Find by ID */
    public static function find(int $id): ?self
    {
        $db  = new Database();
        $row = $db->query('SELECT id, title FROM events WHERE id = :id')
            ->bind(':id', $id)
            ->single();

        return $row ? self::fromRow($row) : null;
    }

    /** List with pagination */
    public static function list(int $limit = 20, int $offset = 0): array
    {
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $db    = new Database();
        $total = (int)$db->query('SELECT COUNT(*) FROM events')->value();

        // Using int-cast interpolation for LIMIT/OFFSET (compatible with native preparing)
        $rows = $db->query("SELECT id, title FROM events ORDER BY id DESC LIMIT :limit OFFSET :offset")
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }



    //add validation

}
