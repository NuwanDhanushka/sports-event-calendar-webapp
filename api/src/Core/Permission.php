<?php

namespace App\Core;

class Permission
{
    public static function has(string $permissionKey): bool
    {
        $permissionArray = Session::get('permissions', []);
        return in_array($permissionKey, $permissionArray, true);
    }

    public static function getPermissionsForUser(int $userId): array
    {
        if ($userId <= 0) {
            Session::remove('permissions');
        }

        $database = new Database();
        $database->query(
            'SELECT DISTINCT p.`key`
                   FROM user_roles ur
                   JOIN permissions_roles pr ON pr.role_id = ur.role_id
                   JOIN permissions p      ON p.id = pr.permission_id
                  WHERE ur.user_id = :userId'
        );
        $database->bind(':userId', $userId);
        $permissionsArray = $database->results();

        $permissionsArray = array_map(fn($r) => (string)$r['key'], $permissionsArray);

        Session::set('permissions', $permissionsArray);
        return $permissionsArray;
    }

    public static function getAll(): array
    {
        return Session::get('permissions', []);
    }

    public static function clear(): void
    {
        Session::remove('permissions');
    }

}