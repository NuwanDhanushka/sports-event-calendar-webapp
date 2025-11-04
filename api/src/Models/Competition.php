<?php

namespace App\Models;

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