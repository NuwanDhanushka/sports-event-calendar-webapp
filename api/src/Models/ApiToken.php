<?php

namespace App\Models;

use App\Core\Database;

/**
 * API token model
 * Represents an API token in the system
 * A token can be used to authenticate API requests
 */
class ApiToken {

    private int $id = 0;

    private $token = '';

    private $generatedBy = '';

    public function __construct(array $data = []) {
        if (array_key_exists('id', $data))    $this->id = (int)$data['id'];
        if (array_key_exists('token', $data)) $this->token = (string)$data['token'];
        if (array_key_exists('generatedBy', $data)) $this->generatedBy = (string)$data['generatedBy'];
    }

    /**
     * Create a new API token
     * @param int $generatedBy
     * @param int $bytes
     * @return array
     * @throws \Random\RandomException
     */
    public static function create(int $generatedBy, int $bytes = 32): array
    {
        /** generate url safe base64 token */
        $plain = rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');

        /** get to hash the of the token */
        $hash  = hash('sha256', $plain);

        /** save the hash to the database */
        $db = new Database();
        $db->query('INSERT INTO api_tokens (token_hash, generated_by) VALUES (:hash, :user_id)')
            ->bind(':hash', $hash)
            ->bind(':user_id', $generatedBy)
            ->execute();

        /** return the token and its ID */
        return ['id' => $db->lastId(), 'token' => $plain];
    }

    /**
     * Verify a token
     * @param string $token
     * @return bool
     */
    public static function verify(string $token): bool
    {
        /** check if the token is empty */
        if ($token === '') return false;

        /** get the hash of the token */
        $hash = hash('sha256', $token);

        /** check if the hash of the token exists in the database */
        $row = (new Database())
            ->query('SELECT id FROM api_tokens WHERE token_hash = :h LIMIT 1')
            ->bind(':h', $hash)
            ->single();
        return (bool)$row;
    }

    /**
     * List all tokens (no plaintext)
     * @return array
     */
    public static function listAll(): array
    {
        /** get all tokens from the database */
        return (new Database())
            ->query('SELECT id, generated_by FROM api_tokens ORDER BY id DESC')
            ->results();
    }

    /**
     * Delete a token
     * @param int $id
     * @return int
     */
    public static function delete(int $id): int
    {
        /** delete the token by id */
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