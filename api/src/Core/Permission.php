<?php

namespace App\Core;

/**
 * Permission class
 * Handles permissions
 * Permissions are stored in the session
 */
class Permission
{

    /**
     * Check if the user has a specific permission
     * @param string $permission
     * @return bool
     */
    public static function has(string $permission): bool
    {
        $permissionArray = Session::get('permissions', []);
        return in_array($permission, $permissionArray, true);
    }

    /**
     * Get permissions for a user from DB by user id, store them in session, and return them.
     * Overwrites any existing session cache.
     * @param int $userId
     * @return array
     */
    public static function getPermissionsForUser(int $userId): array
    {
        /** if user id is 0, clear the session */
        if ($userId <= 0) {
            Session::remove('permissions');
        }

        /** get permissions for the user by user id */
        $database = new Database();
        $database->query(
            'SELECT DISTINCT p.name
                   FROM user_roles ur
                   JOIN role_permissions pr ON pr.role_id = ur.role_id
                   JOIN permissions p      ON p.id = pr.permission_id
                  WHERE ur.user_id = :userId'
        );
        $database->bind(':userId', $userId);
        $permissionsArray = $database->results();

        /** convert the result to an array of permissions name strings */
        $permissionsArray = array_map(fn($row) => (string)$row['name'], $permissionsArray);

        /** store the permissions in the session */
        Session::set('permissions', $permissionsArray);

        /** return the permissions */
        return $permissionsArray;
    }

    /**
     * Get all permissions from session
     * @return array
     */
    public static function getAll(): array
    {
        return Session::get('permissions', []);
    }

    /**
     * Clear the permissions from the session
     * @return void
     */
    public static function clear(): void
    {
        Session::remove('permissions');
    }

}