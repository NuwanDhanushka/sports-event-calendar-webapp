<?php

namespace App\Models;

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