<?php

namespace App\Models;
/**
 * EventTeam model
 * Represents a team in an event
 * A team that belongs to an event
 */
class EventTeam
{
    private int $eventId = 0;
    private int $teamId  = 0;

    private ?string $side = null;    // e.g. 'home' | 'away' | null
    private ?string $score = null;   // Store score as string
    private ?string $result = null;  // e.g. 'win' | 'loss' | 'draw' | null

    private ?Team $team = null;      // optional joined team

    public function __construct(array $data = [])
    {
        $this->eventId = (int)($data['eventId'] ?? $data['event_id'] ?? 0);
        $this->teamId  = (int)($data['teamId']  ?? $data['team_id']  ?? 0);

        $this->side   = $data['side']   ?? null;
        $this->score  = $data['score']  ?? null;
        $this->result = $data['result'] ?? null;

        $this->team = isset($data['team'])
            ? ($data['team'] instanceof Team ? $data['team'] : Team::fromArray($data['team']))
            : null;
    }

    /**
     * Create EventTeam object from database row
     */
    public static function fromRow(array $row): self
    {
        $team = isset($row['team']) ? Team::fromArray($row['team']) : null;

        return new self([
            'eventId' => (int)$row['event_id'],
            'teamId'  => (int)$row['team_id'],
            'side'    => $row['side'] ?? null,
            'score'   => $row['score'] ?? null,
            'result'  => $row['result'] ?? null,
            'team'    => $team,
        ]);
    }

    /**
     * Create EventTeam object from array
     */
    public static function fromArray(?array $data): ?self
    {
        if (!$data) return null;
        return new self($data);
    }

    /**
     * Convert EventTeam object to array
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'teamId'  => $this->teamId,
            'side'    => $this->side,
            'score'   => $this->score,
            'result'  => $this->result,
            'team'    => $this->team?->toArray(),
        ];
    }

}