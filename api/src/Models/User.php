<?php

namespace App\Models;

use App\Core\Database;

/**
 * User model
 * Represents a user in the system
 * A user can have multiple roles and can be an admin, editor, or viewer
 */
class User
{
    private int $id = 0;
    private string $name = '';
    private string $email = '';
    private string $password_hash = '';
    private int $is_active = 1;

    public function __construct(array $data = [])
    {
        if (array_key_exists('id', $data)) $this->id = (int)$data['id'];
        if (array_key_exists('name', $data)) $this->name = (string)$data['name'];
        if (array_key_exists('email', $data)) $this->email = (string)$data['email'];
        if (array_key_exists('password_hash', $data)) $this->password_hash = (string)$data['password_hash'];
        if (array_key_exists('is_active', $data)) $this->is_active = (int)$data['is_active'];
    }

    /**
     * Creates a User object from a database row
     * @param array $data
     * @return self
     */
    public static function fromRow(array $data): self
    {
        return new self($data);
    }

    /**
     * Find a user by ID
     * @param int $id
     * @return self|null
     */
    public static function find(int $id): ?self
    {
        /** get the user data such as id,name,email etc by user id */
        $database = new database();
        $database->query("SELECT id,name,email,password_hash,is_active FROM users WHERE id = :id");
        $database->bind(':id', $id);
        $row = $database->single();
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find a user by email address
     * @param string $email
     * @return self|null
     */
    public static function findByEmail(string $email): ?self
    {
        /** get the user data such as id,name,email etc by user email */
        $database = new database();
        $database->query("SELECT id,name,email,password_hash,is_active FROM users WHERE email = :email");
        $database->bind(':email', $email);
        $row = $database->single();
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Create a new user
     * @param string $name
     * @param string $email
     * @param string $passwordHash
     * @param int $roleId
     * @return int
     */
    public static function create(string $name, string $email, string $passwordHash,int $roleId): int
    {

        /** lower case the email and hash the password */
        $email = strtolower(trim($email));
        $hash = password_hash($passwordHash, PASSWORD_DEFAULT);

        $database = new database();

        try{

            /** begin transaction */
            $database->begin();

            $database->query("
            INSERT INTO users (name, email, password_hash) 
            VALUES (:name, :email, :password_hash)
            ");
            $database->bind(':name', $name);
            $database->bind(':email', $email);
            $database->bind(':password_hash', $hash);

            /** execute the query and get the last inserted user id */
            $database->execute();
            $userId = $database->lastId();

            $database->query("
            INSERT INTO user_roles (user_id, role_id)
            VALUES (:user_id, :role_id)
            ");
            $database->bind(':user_id', $userId);
            $database->bind(':role_id', $roleId);
            $database->execute();

            /** add user id and role id to the user_role table */

            /** commit the transaction */
            $database->commit();

            /** return the user id */
            return $userId;

        }catch(\Exception $e){
            /** rollback the transaction if there is an error*/
            $database->rollBack();
            return 0;
        }
    }

    /**
     * Update a user's profile
     * @param int $id
     * @param array $payload
     * @return int
     */
    public static function updateProfile(int $id, array $payload): int
    {

        $sets = [];
        $bind = [':id' => $id];

        /** check if the name or email is sent in the payload and make the binding */
        if (array_key_exists('name', $payload)) {
            $sets[] = 'name = :name';
            $bind[':name'] = (string)$payload['name'];
        }
        if (array_key_exists('email', $payload)) {
            $sets[] = 'email = :email';
            $bind[':email'] = strtolower(trim((string)$payload['email']));
        }

        /** if no sets are found return 0 */
        if (!$sets) return 0;

        /** Prepare the UPDATE statement: join pieces like ["name = :name", "email = :email"] into
        "name = :name, email = :email", then target the row with the bound :id. */

        $database = new database();
        $database->query('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $database->bindAll($bind);
        $database->execute();
        return $database->rowCount();
    }

    /**
     * Deactivate a user
     * @param int $id
     * @return int
     */
    public static function deactivate(int $id): int
    {
        /** update the user is_active to 0 by the user id to deactivate the user */
        $database = new database();
        $database->query('UPDATE users SET is_active = 0 WHERE id = :id');
        $database->bind(':id', $id);
        $database->execute();
        return $database->rowCount();
    }

    /**
     * Change a user's password
     * @param int $id
     * @param string $newPassword
     * @return int
     */
    public static function changePassword(int $id, string $newPassword): int
    {
        /** get the hash from the new password use default password hashing method */
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        /** update the new password hash in the database by the user id */
        $database = new database();
        $database->query('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $database->bind(':password_hash', $hash);
        $database->bind(':id', $id);
        $database->execute();
        return $database->rowCount();
    }

    /**
     * Validate user credentials
     * @param string $email
     * @param string $password
     * @return self|null
     */
    public static function validateCredentials(string $email, string $password): ?self
    {
        /** check if the user exists by email and check is active*/
        $user = self::findByEmail($email);
        if (!$user || !$user->is_active) return null;

        /** check if the password matches with the hash and return the user obj */
        return password_verify($password, $user->password_hash) ? $user : null;
    }

    /**
     * Creates a User object from an array
     * @param array|null $data
     * @return self|null
     */
    public static function fromArray(?array $data): ?self
    {
        if (!$data) return null;
        return new self([
            'id'        => $data['id']    ?? null,
            'name'      => $data['name']  ?? null,
            'email'     => $data['email'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    /**
     * Return an array representation of the user
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->getId(),
            'name'     => $this->getName(),
            'email'    => $this->getEmail(),
            'isActive' => (bool)$this->getIsActive(),
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPasswordHash(): string
    {
        return $this->password_hash;
    }

    public function setPasswordHash(string $password_hash): void
    {
        $this->password_hash = $password_hash;
    }

    public function getIsActive(): int
    {
        return $this->is_active;
    }

    public function setIsActive(int $is_active): void
    {
        $this->is_active = $is_active;
    }
}