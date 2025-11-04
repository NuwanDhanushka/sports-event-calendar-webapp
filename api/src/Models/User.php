<?php

namespace App\Models;

use App\Core\Database;

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

    public static function fromRow(array $data): self
    {
        return new self($data);
    }

    public static function find(int $id): ?self
    {
        $database = new database();
        $database->query("SELECT id,name,email,password_hash,is_active FROM users WHERE id = :id");
        $database->bind(':id', $id);
        $row = $database->single();
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $database = new database();
        $database->query("SELECT id,name,email,password_hash,is_active FROM users WHERE email = :email");
        $database->bind(':email', $email);
        $row = $database->single();
        return $row ? self::fromRow($row) : null;
    }

    public static function create(string $name, string $email, string $passwordHash,int $roleId): int
    {

        $email = strtolower(trim($email));
        $hash = password_hash($passwordHash, PASSWORD_DEFAULT);

        $database = new database();

        try{

            $database->begin();

            $database->query("
            INSERT INTO users (name, email, password_hash) 
            VALUES (:name, :email, :password_hash)
            ");
            $database->bind(':name', $name);
            $database->bind(':email', $email);
            $database->bind(':password_hash', $hash);
            $database->execute();

            $userId = $database->lastId();

            $database->query("
            INSERT INTO user_roles (user_id, role_id)
            VALUES (:user_id, :role_id)
            ");
            $database->bind(':user_id', $userId);
            $database->bind(':role_id', $roleId);
            $database->execute();

            $database->commit();

            return $userId;

        }catch(\Exception $e){
            $database->rollBack();
            return 0;
        }
    }

    public static function updateProfile(int $id, array $payload): int
    {

        $sets = [];
        $bind = [':id' => $id];

        if (array_key_exists('name', $payload)) {
            $sets[] = 'name = :name';
            $bind[':name'] = (string)$payload['name'];
        }
        if (array_key_exists('email', $payload)) {
            $sets[] = 'email = :email';
            $bind[':email'] = strtolower(trim((string)$payload['email']));
        }

        if (!$sets) return 0;

        $database = new database();
        $database->query('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $database->bindAll($bind);
        $database->execute();
        return $database->rowCount();
    }

    public static function deactivate(int $id): int
    {
        $database = new database();
        $database->query('UPDATE users SET is_active = 0 WHERE id = :id');
        $database->bind(':id', $id);
        $database->execute();
        return $database->rowCount();
    }

    public static function changePassword(int $id, string $newPassword): int
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $database = new database();
        $database->query('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $database->bind(':password_hash', $hash);
        $database->bind(':id', $id);
        $database->execute();
        return $database->rowCount();
    }

    public static function validateCredentials(string $email, string $password): ?self
    {
        $user = self::findByEmail($email);
        if (!$user || !$user->is_active) return null;

        return password_verify($password, $user->password_hash) ? $user : null;
    }

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