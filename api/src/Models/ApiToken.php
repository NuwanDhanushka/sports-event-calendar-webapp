<?php

namespace App\Models;

use App\Core\Database;

class ApiToken {

    private int $id = 0;

    private $token = '';

    private $generatedBy = '';

    public function __construct(array $data = []) {
        if (array_key_exists('id', $data))    $this->id = (int)$data['id'];
        if (array_key_exists('token', $data)) $this->token = (string)$data['token'];
        if (array_key_exists('generatedBy', $data)) $this->generatedBy = (string)$data['generatedBy'];
    }

    public static function create(int $generatedBy, int $bytes = 32): array
    {
        // URL-safe random token
        $plain = rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
        $hash  = hash('sha256', $plain);

        $db = new Database();
        $db->query('INSERT INTO api_tokens (token_hash, generated_by) VALUES (:hash, :user_id)')
            ->bind(':hash', $hash)
            ->bind(':user_id', $generatedBy)
            ->execute();

        return ['id' => $db->lastId(), 'token' => $plain];
    }

    /** Verify a plaintext token */
    public static function verify(string $token): bool
    {
        if ($token === '') return false;
        $hash = hash('sha256', $token);
        $row = (new Database())
            ->query('SELECT id FROM api_tokens WHERE token_hash = :h LIMIT 1')
            ->bind(':h', $hash)
            ->single();
        return (bool)$row;
    }

    /** List all tokens (no plaintext) */
    public static function listAll(): array
    {
        return (new Database())
            ->query('SELECT id, generated_by FROM api_tokens ORDER BY id DESC')
            ->results();
    }

    /** Delete a token by id */
    public static function delete(int $id): int
    {
        return (new Database())
            ->query('DELETE FROM api_tokens WHERE id = :id')
            ->bind(':id', $id)
            ->execute();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getGeneratedBy(): string
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(string $generatedBy): void
    {
        $this->generatedBy = $generatedBy;
    }

}