<?php

namespace App\Models;

use App\Core\Database;

/**
 * Sport model
 * Represents a sport in the database
 * A sport can be a team sport or an individual sport
 */
class Sport
{

    private int $id = 0;
    private string $name = '';
    private int $isTeamSport = 0;

    public function __construct(array $data = [])
    {
        $this->id           = (int)($data['id'] ?? 0);
        $this->name         = (string)($data['name'] ?? '');

        if (array_key_exists('isTeamSport', $data)) {
            $this->isTeamSport = (int)!!$data['isTeamSport'];
        } elseif (array_key_exists('is_team_sport', $data)) {
            $this->isTeamSport = (int)!!$data['is_team_sport'];
        }
    }

    /**
     * Creates a Sport object from a database row
     */
    public static function fromRow(array $row): self
    {
        return new self([
            'id'           => (int)$row['id'],
            'name'         => (string)$row['name'],
            'isTeamSport'  => (int)$row['is_team_sport'],
        ]);
    }

    /**
     * Creates a Sport object from an array
     */
    public static function fromArray(?array $data): ?self
    {
        if (!$data) return null;
        return new self($data);
    }

    /**
     * Converts the Sport object to an array
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'isTeamSport'  => $this->isTeamSport,
        ];
    }

    /**
     * List sports, with paging and filters.
     * @param int $limit
     * @param int $offset
     * @param array $filters
     * @return array
     */
    public static function list(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $db = new Database();

        $where = [];
        $bind  = [];

        if (!empty($filters['q'])) {
            $where[] = '(name LIKE :q)';
            $bind[':q'] = '%'.trim((string)$filters['q']).'%';
        }
        if (isset($filters['team_only'])) {
            $where[] = 'is_team_sport = :team_only';
            $bind[':team_only'] = (int)(bool)$filters['team_only'];
        }

        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        $total = (int)$db->query('SELECT COUNT(*) FROM sports'.$whereSql)
            ->bindAll($bind)
            ->value();

        $rows = $db->query('SELECT id, name, is_team_sport
                            FROM sports'.$whereSql.'
                            ORDER BY name ASC
                            LIMIT :limit OFFSET :offset')
            ->bindAll($bind)
            ->bind(':limit',  $limit)
            ->bind(':offset', $offset)
            ->results();

        $items = array_map(fn($item) => self::fromRow($item), $rows);

        return ['data' => $items, 'total' => $total];
    }

    /** Fetch all (no paging), with filters. */
    public static function all(array $filters = []): array
    {
        $db = new Database();

        $where = [];
        $bind  = [];

        if (!empty($filters['q'])) {
            $where[] = '(name LIKE :q)';
            $bind[':q'] = '%'.trim((string)$filters['q']).'%';
        }
        if (isset($filters['team_only'])) {
            $where[] = 'is_team_sport = :team_only';
            $bind[':team_only'] = (int)(bool)$filters['team_only'];
        }

        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        $rows = $db->query('SELECT id, name, is_team_sport
                            FROM sports'.$whereSql.'
                            ORDER BY name ASC')
            ->bindAll($bind)
            ->results();

        return array_map(fn($item) => self::fromRow($item), $rows);
    }

}