<?php

namespace App\Models;

use App\Core\Database;

/**
 * Competition model
 * Represents a competition (e.g. a league, a tournament, etc.)
 * A competition belongs to a sport and has a list of teams
 */
class Competition
{
    private int $id = 0;
    private ?string $type = null;
    private ?string $name = null;
    private ?int $createdById = null;
    private ?int $sportId = null;

    private ?User $createdBy = null;
    private ?Sport $sport = null;
    /** @var Team[] */
    private array $teams = [];

    public function __construct(array $data = [])
    {
        $this->id          = (int)($data['id'] ?? 0);
        $this->type        = $data['type'] ?? null;
        $this->name        = $data['name'] ?? null;

        $this->createdById = isset($data['createdById']) ? (int)$data['createdById']
            : (isset($data['created_by']) ? (int)$data['created_by'] : null);
        $this->sportId     = isset($data['sportId']) ? (int)$data['sportId']
            : (isset($data['sport_id']) ? (int)$data['sport_id'] : null);

        $this->createdBy   = isset($data['createdBy'])
            ? ($data['createdBy'] instanceof User ? $data['createdBy'] : User::fromArray($data['createdBy']))
            : (isset($data['created_by']) ? User::fromArray($data['created_by']) : null);

        $this->sport       = isset($data['sport'])
            ? ($data['sport'] instanceof Sport ? $data['sport'] : Sport::fromArray($data['sport']))
            : null;

        $this->teams = [];
        if (!empty($data['teams']) && is_array($data['teams'])) {
            foreach ($data['teams'] as $teamData) {
                $team = $teamData instanceof Team ? $teamData : Team::fromArray($teamData);
                if ($team) $this->teams[] = $team;
            }
        }
    }


    /**
     * Creates a Competition object from a database row
     * @param array $data
     * @return self
     */
    public static function fromRow(array $data): self
    {
        $createdBy = isset($data['created_by']) ? User::fromArray($data['created_by']) : null;
        $sport     = isset($data['sport']) ? Sport::fromArray($data['sport']) : null;

        $teams = [];
        if (!empty($data['teams']) && is_array($data['teams'])) {
            foreach ($data['teams'] as $teamData) {
                $obj = Team::fromArray($teamData);
                if ($obj) $teams[] = $obj;
            }
        }

        return new self([
            'id'          => (int)$data['id'],
            'type'        => $data['type'] ?? null,
            'name'        => $data['name'] ?? null,
            'createdById' => isset($data['created_by']) ? (int)$data['created_by'] : null,
            'sportId'     => isset($data['sport_id']) ? (int)$data['sport_id'] : null,
            'createdBy'   => $createdBy,
            'sport'       => $sport,
            'teams'       => $teams,
        ]);
    }

    /**
     * Creates a Competition object from an array
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

        /** execute and get the total number of competitions */
        $total = (int)$db->query('SELECT COUNT(*) FROM competitions'.$whereSql)
            ->bindAll($bind)
            ->value();

        /** execute and get the competitions */
        $rows = $db->query('SELECT id, type, name, sport_id
                            FROM competitions'.$whereSql.'
                            ORDER BY name ASC
                            LIMIT :limit OFFSET :offset')
            ->bindAll($bind)
            ->bind(':limit',  $limit)
            ->bind(':offset', $offset)
            ->results();

        /** map the competitions to Competition objects */
        $items = array_map(fn($item) => self::fromRow($item), $rows);

        /** return the competitions and total number of competitions */
        return ['data' => $items, 'total' => $total];
    }

    /**
     * Fetch all competitions, optionally filtered by sport.
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

        /** execute and get the competitions */
        $rows = $db->query('SELECT id, type, name, sport_id
                            FROM competitions'.$whereSql.'
                            ORDER BY name ASC')
            ->bindAll($bind)
            ->results();

        /** map the competitions to Competition objects */
        return array_map(fn($item) => self::fromRow($item), $rows);
    }

    /**
     * Get all teams for a given competition.
     *
     * @param int $competitionId
     * @return Team[]
     */
    public static function teams(int $competitionId): array
    {
        $db = new Database();

        /** get the teams for the competition by competition_id joining with teams table */
        $rows = $db->query(
            'SELECT t.*
             FROM competition_teams ct
             INNER JOIN teams t ON t.id = ct.team_id
             WHERE ct.competition_id = :cid
             ORDER BY t.name ASC'
        )
            ->bind(':cid', $competitionId)
            ->results();

        /** map the teams to Team objects */
        return array_map(
            fn($row) => Team::fromArray($row),
            $rows
        );
    }

    /**
     * Converts the Competition object to an array
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'name'        => $this->name,
            'createdById' => $this->createdById,
            'sportId'     => $this->sportId,
            'createdBy'   => $this->createdBy?->toArray(),
            'sport'       => $this->sport?->toArray(),
            'teams'       => array_map(fn($team) => $team->toArray(), $this->teams),
        ];
    }

}