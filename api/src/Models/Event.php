<?php

namespace App\Models;

use App\Core\Database;

/**
 * Event model
 * Represents an event in the system
 * A single event can have multiple teams
 */

class Event
{
    private int $id = 0;
    private ?string $title = null;
    private ?string $bannerPath = null;
    private ?string $description = null;
    private ?string $status = null;
    private string $startAt = '';
    private ?string $endAt = null;

    // Foreign keys
    private ?int $competitionId = null;
    private ?int $venueId = null;
    private ?int $sportId = null;
    private ?int $createdById = null;

    private ?Venue $venue = null;
    private ?Sport $sport = null;
    private ?Competition $competition = null;
    private ?User $createdBy = null;

    /** @var EventTeam[] */
    private array $teams = [];

    public function __construct(array $data = [])
    {
        $this->id         = (int)($data['id'] ?? 0);
        $this->title      = isset($data['title']) ? (string)$data['title'] : '';
        $this->bannerPath = $data['bannerPath'] ?? $data['banner_path'] ?? null;

        $this->description = $data['description'] ?? null;
        $this->status      = $data['status'] ?? null;
        $this->startAt     = (string)($data['startAt'] ?? $data['start_at'] ?? '');
        $this->endAt       = $data['endAt'] ?? $data['end_at'] ?? null;

        $this->competitionId = isset($data['competitionId']) ? (int)$data['competitionId']
            : (isset($data['competition_id']) ? (int)$data['competition_id'] : null);
        $this->venueId       = isset($data['venueId']) ? (int)$data['venueId']
            : (isset($data['venue_id']) ? (int)$data['venue_id'] : null);
        $this->sportId       = isset($data['sportId']) ? (int)$data['sportId']
            : (isset($data['sport_id']) ? (int)$data['sport_id'] : null);
        $this->createdById   = isset($data['createdById']) ? (int)$data['createdById']
            : (isset($data['created_by']) ? (int)$data['created_by'] : null);

        $this->venue       = isset($data['venue'])
            ? ($data['venue'] instanceof Venue ? $data['venue'] : Venue::fromArray($data['venue']))
            : null;

        $this->sport       = isset($data['sport'])
            ? ($data['sport'] instanceof Sport ? $data['sport'] : Sport::fromArray($data['sport']))
            : null;

        $this->competition = isset($data['competition'])
            ? ($data['competition'] instanceof Competition ? $data['competition'] : Competition::fromArray($data['competition']))
            : null;

        $this->createdBy   = isset($data['createdBy'])
            ? ($data['createdBy'] instanceof User ? $data['createdBy'] : User::fromArray($data['createdBy']))
            : (isset($data['created_by']) ? User::fromArray($data['created_by']) : null);

        $this->teams = [];
        if (!empty($data['teams']) && is_array($data['teams'])) {
            foreach ($data['teams'] as $eventTeam) {
                $this->teams[] = $eventTeam instanceof EventTeam ? $eventTeam : EventTeam::fromArray($eventTeam);
            }
        }
    }

    /**
     * Return an event object from a database row
     * @param array $data
     * @return self
     */
    public static function fromRow(array $data): self
    {

        /** Hydrate nested objects if present in the array payload */
        $venue       = isset($data['venue'])       ? Venue::fromArray($data['venue'])             : null;
        $sport       = isset($data['sport'])       ? Sport::fromArray($data['sport'])             : null;
        $competition = isset($data['competition']) ? Competition::fromArray($data['competition']) : null;
        $createdBy   = isset($data['created_by'])  ? User::fromArray($data['created_by'])     : null;

        /** Build EventTeam[] from the 'teams' list (ignore non-array / empty) */
        $eventTeams = [];
        if (!empty($data['teams']) && is_array($data['teams'])) {
            foreach ($data['teams'] as $eventTeam) {
                $obj = EventTeam::fromArray($eventTeam);
                if ($obj) $eventTeams[] = $obj;
            }
        }

        /** return fully constructed event object */
        return new self([
            'id'            => (int)$data['id'],
            'title'         => (string)$data['title'],
            'bannerPath'    => $data['banner_path'] ?? null,
            'description'   => $data['description'] ?? null,
            'status'        => $data['status'] ?? null,
            'startAt'       => (string)$data['start_at'],
            'endAt'         => $data['end_at'] ?? null,
            'competitionId' => isset($data['competition_id']) ? (int)$data['competition_id'] : null,
            'venueId'       => isset($data['venue_id'])       ? (int)$data['venue_id']       : null,
            'sportId'       => isset($data['sport_id'])       ? (int)$data['sport_id']       : null,
            'createdById'   => isset($data['created_by'])     ? (int)$data['created_by']     : null,
            'venue'         => $venue,
            'sport'         => $sport,
            'competition'   => $competition,
            'createdBy'     => $createdBy,
            'teams'         => $eventTeams,
        ]);
    }

    /**
     * Return an array representation of the event
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'bannerPath'    => $this->bannerPath,
            'description'   => $this->description,
            'status'        => $this->status,
            'startAt'       => $this->startAt,
            'endAt'         => $this->endAt,
            'competitionId' => $this->competitionId,
            'venueId'       => $this->venueId,
            'sportId'       => $this->sportId,
            'createdById'   => $this->createdById,

            'venue'         => $this->venue?->toArray(),
            'sport'         => $this->sport?->toArray(),
            'competition'   => $this->competition?->toArray(),
            'createdBy'     => $this->createdBy?->toArray(),
            'teams'         => array_map(fn($eventTeam) => $eventTeam->toArray(), $this->teams),
        ];
    }

    private static function validate(array $data): void
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') throw new \InvalidArgumentException('title is required');
    }

    /**
     * Create a new event
     * @param array $data
     * @return int
     */
    public static function create(array $data, ?string $bannerPath = null): int
    {
        /** Validate the data */
        self::validate($data);

        $database = new Database();

        $sql = 'INSERT INTO events (title, banner_path, description, start_at, end_at, status, competition_id, venue_id, sport_id, created_by)
                VALUES (:title, :banner_path, :description, :start_at, :end_at, :status, :competition_id, :venue_id, :sport_id, :created_by)';

        $database->query($sql);
        $database->bind(':title',         (string)$data['title']);
        $database->bind(':banner_path',   $bannerPath);
        $database->bind(':description',   $data['description'] ?? null);
        $database->bind(':start_at',      $data['start_at'] ?? date('Y-m-d H:i:s'));
        $database->bind(':end_at',        $data['end_at'] ?? null);
        $database->bind(':status',        $data['status'] ?? null);
        $database->bind(':competition_id',$data['competition_id'] ?? null);
        $database->bind(':venue_id',      $data['venue_id'] ?? null);
        $database->bind(':sport_id',      $data['sport_id'] ?? null);
        $database->bind(':created_by',    $data['created_by'] ?? null);
        $database->execute();
        /** return the last inserted event id after insert execution */
        return $database->lastId();
    }

    /**
     * Base SELECT query for to get event data with nested JSON relations:
     *   venue, sport, competition and teams[] (each with team & sport).
     *   Uses LEFT JOINs + COALESCE to return {} / [] when relations are missing.
     */
    private static function baseSelect(): string
    {
        return "
SELECT
  e.id, e.title, e.banner_path, e.description, e.status, e.start_at, e.end_at,
  e.competition_id, e.venue_id, e.sport_id, e.created_by,

  COALESCE(JSON_OBJECT(
    'id', v.id,
     'name', v.name,
    'address_line1', v.address_line1,
     'address_line2', v.address_line2,
    'city', v.city,
     'postal_code', v.postal_code,
      'country', v.country,
    'is_indoor', v.is_indoor,
     'time_zone', v.time_zone
  ), JSON_OBJECT()) AS venue,

  COALESCE(JSON_OBJECT(
    'id', s.id,
     'name', s.name,
      'is_team_sport', s.is_team_sport
  ), JSON_OBJECT()) AS sport,

  COALESCE(JSON_OBJECT(
    'id', c.id,
    'type', c.type,
    'name', c.name,
    'sport_id', c.sport_id,
    'createdById', c.created_by,
    'sport', COALESCE(JSON_OBJECT(
        'id', cs.id, 'name', cs.name, 'is_team_sport', IFNULL(cs.is_team_sport, 0)
    ), JSON_OBJECT()),
    'created_by', COALESCE(JSON_OBJECT(
        'id', cu.id, 'name', cu.name, 'email', cu.email, 'is_active', IFNULL(cu.is_active, 1)
    ), JSON_OBJECT())
  ), JSON_OBJECT()) AS competition,

  COALESCE(JSON_OBJECT('id', u.id, 'name', u.name, 'email', u.email), JSON_OBJECT()) AS created_by,

COALESCE((
  SELECT JSON_ARRAYAGG(
           JSON_OBJECT(
             'event_id', et.event_id,
             'team_id',  et.team_id,
             'side',     et.side,
             'score',    et.score,
             'result',   et.result,
             'team', JSON_OBJECT(
               'id', t.id,
                'name', t.name,
                 'short_name', t.short_name,
                  'logo_url', t.logo_url,
               'city', t.city, 
               'country', t.country,
                'sport_id', t.sport_id,
                      'sport', COALESCE(JSON_OBJECT(
          'id', ts.id,
          'name', ts.name,
          'is_team_sport', IFNULL(ts.is_team_sport, 0)
        ), JSON_OBJECT())
             )
           )
         )
  FROM event_teams et
  JOIN teams t       ON t.id = et.team_id
  LEFT JOIN sports ts ON ts.id = t.sport_id
  WHERE et.event_id = e.id
), JSON_ARRAY()) AS teams

FROM events e
LEFT JOIN venues       v ON v.id = e.venue_id
LEFT JOIN sports       s ON s.id = e.sport_id
LEFT JOIN competitions c ON c.id = e.competition_id
LEFT JOIN users        u ON u.id = e.created_by
LEFT JOIN sports       cs ON cs.id = c.sport_id 
LEFT JOIN users        cu ON cu.id = c.created_by  
";
    }

    /**
     * Find event by id.
     * Returns null if not found. Returns fully constructed event object if found.
     * @param int $id
     * @return self|null
     */
    public static function find(int $id): ?self
    {
        $db = new Database();

        /** get the base query and append WHERE id = :id to get the record by id */
        $sql = self::baseSelect() . ' WHERE e.id = :id LIMIT 1';
        $row = $db->query($sql)->bind(':id', $id)->single();
        if (!$row) return null;

        /** decode json fields */
        $row['venue']       = $row['venue']       ? json_decode($row['venue'], true)       : [];
        $row['sport']       = $row['sport']       ? json_decode($row['sport'], true)       : [];
        $row['competition'] = $row['competition'] ? json_decode($row['competition'], true) : [];
        $row['created_by']  = $row['created_by']  ? json_decode($row['created_by'], true)  : [];
        $row['teams']       = $row['teams']       ? json_decode($row['teams'], true)       : [];

        /** return fully constructed event object */
        return self::fromRow($row);
    }

    /**
     * List all events.
     * Returns an array of fully constructed event objects.
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function list(int $limit = 20, int $offset = 0): array
    {
        /** validate limit and offset */
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $db = new Database();

        /** get the total number of events */
        $total = (int)$db->query('SELECT COUNT(*) FROM events')->value();

        /** get the base query and append LIMIT and OFFSET to get the records */
        $sql = self::baseSelect() . ' ORDER BY e.id DESC LIMIT :limit OFFSET :offset';
        $q = $db->query($sql)
            ->bind(':limit',  $limit)
            ->bind(':offset', $offset);

        $rows = $q->results();

        /** decode json fields */
        foreach ($rows as &$row) {
            $row['venue']       = $row['venue']       ? json_decode($row['venue'], true)       : [];
            $row['sport']       = $row['sport']       ? json_decode($row['sport'], true)       : [];
            $row['competition'] = $row['competition'] ? json_decode($row['competition'], true) : [];
            $row['created_by']  = $row['created_by']  ? json_decode($row['created_by'], true)  : [];
            $row['teams']       = $row['teams']       ? json_decode($row['teams'], true)       : [];
        }

       /** unset the row to free up memory */
        unset($row);

        /** return an array of fully constructed event objects */
        $items = array_map(fn($row) => self::fromRow($row), $rows);
        return ['data' => $items, 'total' => $total];
    }


    /**
     * This function is used to filter events by sports, date range and search.
     * @param string|null $from
     * @param string|null $to
     * @param array $filters
     * @return array
     */
    public static function filterEvents(?string $from, ?string $to, array $filters = []): array
    {
        /** if from or to is null, set it to open-ended */
        if ($from === null || $from === '') $from = '0000-01-01 00:00:00';
        if ($to   === null || $to   === '') $to   = '9999-12-31 23:59:59';

        /** if the range doesn't have a time component, add it */
        if (strlen($from) === 10) $from .= ' 00:00:00';
        if (strlen($to)   === 10) $to   .= ' 23:59:59';
        if ($from > $to) [ $from, $to ] = [ $to, $from ];

        $db = new Database();

        /** build the WHERE clause with start and end date range */
        $where = [
            '(e.start_at <= :to AND (e.end_at IS NULL OR e.end_at >= :from))'
        ];

        /** the bind to from and to */
        $bind  = [ ':from' => $from, ':to' => $to ];

        /** if there is a search query, add it to the WHERE clause */
        if (!empty($filters['q'])) {
            $where[] = '(e.title LIKE :q)';
            $bind[':q'] = '%'.trim((string)$filters['q']).'%';
        }

        /** Sports filter
         * If string: split CSV by ',', trim items, drop empties → array
         * If array: use as-is
         * Otherwise: wrap non-empty scalar in an array; else []
         */
        $sports = $filters['sports'] ?? [];
        if (is_string($sports)) {
            $sports = array_filter(array_map('trim', explode(',', $sports)), 'strlen');
        } elseif (!is_array($sports)) {
            $sports = ($sports !== null && $sports !== '') ? [ $sports ] : [];
        }

        if ($sports) {
            /** Splits value into numeric id and string names */
            $ids = []; $names = [];
            foreach ($sports as $value) {
                if (is_numeric($value)) $ids[] = (int)$value;
                else $names[] = (string)$value;
            }

            /** Build parameterized IN() for sport IDs: e.sport_id IN (:sid0, :sid1, ...) */
            if ($ids) {
                $in = [];
                foreach ($ids as $i => $id) {
                    $key = ":sid{$i}";
                    $in[] = $key; // collect placeholder
                    $bind[$key] = $id;  // bind actual value
                }
                $where[] = 'e.sport_id IN ('.implode(',', $in).')';
            }

            /** Build parameterized IN() for sport names: s.name IN (:sname0, :sname1, ...) */
            if ($names) {
                $in = [];
                foreach ($names as $i => $name) {
                    $key = ":sname{$i}";
                    $in[] = $key;   // collect placeholder
                    $bind[$key] = $name;  // bind actual value
                }
                $where[] = 's.name IN ('.implode(',', $in).')';
            }
        }

        /** append where conditions to the base SELECT query*/
        $sql = self::baseSelect()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY (e.start_at IS NULL), e.start_at, e.id';

        $db->query($sql);

        /** bind the parameters */
        foreach ($bind as $key => $value) $db->bind($key, $value);

        /** execute the query and get the results */
        $rows = $db->results();

        /** decode json fields */
        foreach ($rows as &$row) {
            $row['venue']       = $row['venue']       ? json_decode($row['venue'], true)       : [];
            $row['sport']       = $row['sport']       ? json_decode($row['sport'], true)       : [];
            $row['competition'] = $row['competition'] ? json_decode($row['competition'], true) : [];
            $row['created_by']  = $row['created_by']  ? json_decode($row['created_by'], true)  : [];
            $row['teams']       = $row['teams']       ? json_decode($row['teams'], true)       : [];
        }

        /** unset the row to free up memory */
        unset($row);

        /** return an array of fully constructed event objects */
        return array_map(fn($row) => self::fromRow($row), $rows);
    }


    /**
     * Update event data by id.
     * @param int $id
     * @param array $payload
     * @return int
     */
    public static function update(int $id, array $payload): int
    {
        /** check if none of the allowed fields are present, do nothing */
        if (!array_key_exists('title', $payload)
            && !array_key_exists('description', $payload)
            && !array_key_exists('status', $payload)
            && !array_key_exists('start_at', $payload)
            && !array_key_exists('end_at', $payload)
            && !array_key_exists('competition_id', $payload)
            && !array_key_exists('venue_id', $payload)
            && !array_key_exists('sport_id', $payload)
        ) return 0;

        /** Build dynamic SET clauses and their bound values */
        $sets = [];
        $bind = [];
        foreach ([
                     'title','description','status','start_at','end_at',
                     'competition_id','venue_id','sport_id'
                 ] as $column) {
            if (array_key_exists($column, $payload)) {
                $sets[] = "$column = :$column"; // e.g. "title = :title"
                $bind[":$column"] = $payload[$column]; // parameter value
            }
        }

        /** Run model validation for fields that require.*/
        if (isset($bind[':title'])) self::validate(['title' => $bind[':title']]);

        /** implode the data and execute the update query */
        $sql = 'UPDATE events SET '.implode(', ', $sets).' WHERE id = :id';
        $db = new Database();
        $db->query($sql);
        foreach ($bind as $key => $value) $db->bind($key, $value);
        $db->bind(':id', $id);
        $db->execute();

        /** return the number of affected rows */
        return $db->rowCount();
    }

    /**
     * Delete event by id.
     * @param int $id
     * @return int
     */
    public static function delete(int $id): int
    {
        $db = new Database();

        $db->query('DELETE FROM events WHERE id = :id');
        $db->bind(':id', $id);
        $db->execute();
        return $db->rowCount();
    }

    /**
     * Set relative banner image path for event and update the database.
     * @param int $id
     * @param string|null $relativePath
     * @return int
     */
    public static function setBanner(int $id, ?string $relativePath): int
    {
        $database = new Database();
        $database->query('UPDATE events SET banner_path = :path WHERE id = :id');
        $database->bind(':path', $relativePath);
        $database->bind(':id', $id);
        $database->execute();
        return $database->rowCount();
    }

    /**
     * Fetch the banner path for the event by id.
     * @param int $id
     * @return string|null
     */
    public static function getBannerPathById(int $id): ?string
    {
        $database = new Database();
        $database->query('SELECT banner_path FROM events WHERE id = :id');
        $database->bind(':id', $id);
        $row = $database->single();
        return $row ? ($row['banner_path'] ?? null) : null;
    }

    public function getId(): int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function getBannerPath(): ?string { return $this->bannerPath; }
    public function getDescription(): ?string { return $this->description; }
    public function getStatus(): ?string { return $this->status; }
    public function getStartAt(): string { return $this->startAt; }
    public function getEndAt(): ?string { return $this->endAt; }
    public function getCompetitionId(): ?int { return $this->competitionId; }
    public function getVenueId(): ?int { return $this->venueId; }
    public function getSportId(): ?int { return $this->sportId; }
    public function getCreatedById(): ?int { return $this->createdById; }

    public function getVenue(): ?Venue { return $this->venue; }
    public function getSport(): ?Sport { return $this->sport; }
    public function getCompetition(): ?Competition { return $this->competition; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    /** @return EventTeam[] */
    public function getTeams(): array { return $this->teams; }

    public function setBannerPath(?string $bannerPath): void { $this->bannerPath = $bannerPath; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function setStatus(?string $status): void { $this->status = $status; }
    public function setStartAt(string $startAt): void { $this->startAt = $startAt; }
    public function setEndAt(?string $endAt): void { $this->endAt = $endAt; }
    public function setCompetitionId(?int $competitionId): void { $this->competitionId = $competitionId; }
    public function setVenueId(?int $venueId): void { $this->venueId = $venueId; }
    public function setSportId(?int $sportId): void { $this->sportId = $sportId; }
    public function setCreatedById(?int $createdById): void { $this->createdById = $createdById; }

}