<?php

namespace App\Models;

use App\Core\Database;

/**
 * Venue model
 * Represents a venue in the database
 * A venue can be a sporting facility, a stadium, etc. belonging to a event
 */
class Venue {

    private int $id = 0;
    private string $name = '';
    private string $addressLine1 = '';
    private ?string $addressLine2 = null;
    private string $city = '';
    private string $postalCode = '';
    private string $country = '';
    private ?int $isIndoor = null;
    private ?string $timeZone = null;

    public function __construct(array $data = []) {

        $this->id           = (int)($data['id'] ?? 0);
        $this->name         = (string)($data['name'] ?? '');

        $this->addressLine1 = (string)($data['addressLine1'] ?? $data['address_line1'] ?? '');
        $this->addressLine2 = $data['addressLine2'] ?? $data['address_line2'] ?? null;

        $this->city         = (string)($data['city'] ?? '');
        $this->postalCode   = (string)($data['postalCode'] ?? $data['postal_code'] ?? '');
        $this->country      = (string)($data['country'] ?? '');

        if (array_key_exists('isIndoor', $data)) {
            $this->isIndoor = $data['isIndoor'] === null ? null : (int)$data['isIndoor'];
        } elseif (array_key_exists('is_indoor', $data)) {
            $this->isIndoor = $data['is_indoor'] === null ? null : (int)$data['is_indoor'];
        }

        $this->timeZone     = $data['timeZone'] ?? $data['time_zone'] ?? null;
    }

    /**
     * Creates a Venue object from a database row
     * @param array $row
     * @return self
     */
    public static function fromRow(array $row): self
    {
        return new self([
            'id'            => (int)$row['id'],
            'name'          => (string)$row['name'],
            'addressLine1'  => (string)$row['address_line1'],
            'addressLine2'  => $row['address_line2'] ?? null,
            'city'          => (string)$row['city'],
            'postalCode'    => (string)$row['postal_code'],
            'country'       => (string)$row['country'],
            'isIndoor'      => array_key_exists('is_indoor', $row) && $row['is_indoor'] !== null ? (int)$row['is_indoor'] : null,
            'timeZone'      => $row['time_zone'] ?? null,
        ]);
    }

    /**
     * Creates a Venue object from an array
     * @param array|null $data
     * @return self|null
     */
    public static function fromArray(?array $data): ?self
    {
        if (!$data) return null;
        return new self($data);
    }

    /**
     * List venues with paging and filters.
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


        /** create the WHERE clause by joining the WHERE clauses with AND */
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        /** execute and get the total number of venues */
        $total = (int)$db->query('SELECT COUNT(*) FROM venues'.$whereSql)
            ->bindAll($bind)
            ->value();

        /** execute and get the venues */
        $rows = $db->query('SELECT     id, name, address_line1, address_line2, city, postal_code, country, is_indoor, time_zone
                            FROM venues' . $whereSql . '
                            ORDER BY name ASC
                            LIMIT :limit OFFSET :offset')
            ->bindAll($bind)
            ->bind(':limit',  $limit)
            ->bind(':offset', $offset)
            ->results();

        /** map the venues to Venue objects */
        $items = array_map(fn($item) => self::fromRow($item), $rows);

        /** return the venues and total number of venues */
        return ['data' => $items, 'total' => $total];
    }

    /**
     * Fetch all venues, optionally filtered by sport.
     * @param array $filters
     * @return array
     */
    public static function all(array $filters = []): array
    {
        $db = new Database();

        $where = [];
        $bind  = [];

        /** create the WHERE clause by joining the WHERE clauses with AND */
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

        /** execute and get the venues */
        $rows = $db->query('SELECT id, name, address_line1, address_line2, city, postal_code, country, is_indoor, time_zone
                            FROM venues'.$whereSql.'
                            ORDER BY name ASC')
            ->bindAll($bind)
            ->results();

        /** map the venues to Venue objects */
        return array_map(fn($item) => self::fromRow($item), $rows);
    }

    /**
     * Converts the Venue object to an array
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'city'         => $this->city,
            'postalCode'   => $this->postalCode,
            'country'      => $this->country,
            'isIndoor'     => $this->isIndoor,
            'timeZone'     => $this->timeZone,
        ];
    }

}