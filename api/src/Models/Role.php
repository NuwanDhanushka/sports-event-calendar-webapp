<?php

namespace App\Models;

use App\Core\Database;

class Role {

    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const VIEWER = 'viewer';
    public const ALL = [self::ADMIN, self::EDITOR, self::VIEWER];

    public const DEFAULT = self::ADMIN;
    public const DEFAULT_ID = 1;

    public static function exists(int $id){
        $db = new Database();
        $db->query('SELECT 1 FROM roles WHERE id = :id');
        $db->bind(':id', $id);
        return (bool)$db->single();
    }

    public static function idByRoleName(string $roleName): ?int
    {
        $db = new Database();
        $db->query('SELECT id FROM roles WHERE name = :name');
        $db->bind(':name', $roleName);
        $row = $db->single();
        return $row ? (int)$row['id'] : null;
    }

}
