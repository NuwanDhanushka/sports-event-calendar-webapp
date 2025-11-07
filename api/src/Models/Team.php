<?php

namespace App\Models;

use App\Core\Database;

/**
 * Team model
 * Represents a team in the database
 * A team can be a sports team, a club, etc. belonging to a competition and an event
 * Team tied with a sport
 */
class Team
{
    private int $id = 0;
    private string $name = '';
    private string $shortName = '';
    private ?string $city = null;
    private ?string $country = null;
    private ?string $logoUrl = null;
    private int $sportId = 0;
    private ?Sport $sport = null;


    public function __construct(array $data = [])
    {
        $this->id        = (int)($data['id'] ?? 0);
        $this->name      = (string)($data['name'] ?? '');
        $this->shortName = (string)($data['shortName'] ?? $data['short_name'] ?? '');
        $this->city      = $data['city'] ?? null;
        $this->country   = $data['country'] ?? null;
        $this->logoUrl   = $data['logoUrl'] ?? $data['logo_url'] ?? null;
        $this->sportId   = isset($data['sportId']) ? (int)$data['sportId']
            : (isset($data['sport_id']) ? (int)$data['sport_id'] : 0);

        $this->sport     = isset($data['sport'])
            ? ($data['sport'] instanceof Sport ? $data['sport'] : Sport::fromArray($data['sport']))
            : null;
    }

    /**
     * Create Team object from database row
     * @param array $row
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $sport = isset($row['sport']) ? Sport::fromArray($row['sport']) : null;

        return new self([
            'id'        => (int)$row['id'],
            'name'      => (string)$row['name'],
            'shortName' => (string)$row['short_name'],
            'city'      => $row['city'] ?? null,
            'country'   => $row['country'] ?? null,
            'logoUrl'   => $row['logo_url'] ?? null,
            'sportId'   => (int)$row['sport_id'],
            'sport'     => $sport,
        ]);
    }

    /**
     * Creates a Team object from an array
     * @param array|null $data
     * @return self|null
     */
    public static function fromArray(?array $data): ?self
    {
        if (!$data) return null;
        return new self($data);
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
        /** limit and offset */
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $db = new Database();

        $where = [];
        $bind  = [];

        /** if there's a sport_id filter, add a WHERE clause by bind the sport_id value' */
        if (isset($filters['sport_id'])) {
            $where[] = 'sport_id = :sport_id';
            $bind[':sport_id'] = (int)$filters['sport_id'];
        }

        /** create the WHERE clause by joining the WHERE clauses with AND */
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        /** execute and get the total number of teams */
        $total = (int)$db->query('SELECT COUNT(*) FROM teams'.$whereSql)
            ->bindAll($bind)
            ->value();

        /** execute and get the teams */
        $rows = $db->query('SELECT id, name, short_name, city, country, logo_url,sport_id
                            FROM teams'.$whereSql.'
                            ORDER BY name ASC
                            LIMIT :limit OFFSET :offset')
            ->bindAll($bind)
            ->bind(':limit',  $limit)
            ->bind(':offset', $offset)
            ->results();

        /** map the teams to team objects */
        $items = array_map(fn($item) => self::fromRow($item), $rows);

        /** return the teams and total number of teams */
        return ['data' => $items, 'total' => $total];
    }

    /**
     * Fetch all teams, optionally filtered by sport.
     * @param array $filters
     * @return array
     */
    public static function all(array $filters = []): array
    {
        $db = new Database();

        $where = [];
        $bind  = [];

        /** if there's a sport_id filter, add a WHERE clause by bind the sport_id value */
        if (isset($filters['sport_id'])) {
            $where[] = 'sport_id = :sport_id';
            $bind[':sport_id'] = (int)(bool)$filters['sport_id'];
        }

        /** create the WHERE clause by joining the WHERE clauses with AND */
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        /** execute and get the teams */
        $rows = $db->query('SELECT id, name, short_name, city, country, logo_url,sport_id
                            FROM teams'.$whereSql.'
                            ORDER BY name ASC')
            ->bindAll($bind)
            ->results();

        /** map the teams to team objects */
        return array_map(fn($item) => self::fromRow($item), $rows);
    }


    /**
     * Converts the Team object to an array
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'shortName' => $this->shortName,
            'city'      => $this->city,
            'country'   => $this->country,
            'logoUrl'   => $this->logoUrl,
            'sportId'   => $this->sportId,
            'sport'     => $this->sport?->toArray(),
        ];
    }
}