<?php

namespace App\Models;

use App\Core\Database;

/**
 * Role model
 * Represents a role in the system
 * A role can be admin, editor, or viewer
 */
class Role {

    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const VIEWER = 'viewer';
    public const ALL = [self::ADMIN, self::EDITOR, self::VIEWER];

    public const DEFAULT = self::ADMIN;
    public const DEFAULT_ID = 1;

    /**
     * Check if a role exists by its ID
     * @param int $id
     * @return bool
     */
    public static function exists(int $id){
        $db = new Database();
        $db->query('SELECT 1 FROM roles WHERE id = :id');
        $db->bind(':id', $id);
        return (bool)$db->single();
    }

    /**
     * Get the ID of a role by its name
     * @param string $roleName
     * @return int|null
     */
    public static function idByRoleName(string $roleName): ?int
    {
        /** get the role id from the role name from the database */
        $db = new Database();
        $db->query('SELECT id FROM roles WHERE name = :name');
        $db->bind(':name', $roleName);
        $row = $db->single();
        return $row ? (int)$row['id'] : null;
    }

}
