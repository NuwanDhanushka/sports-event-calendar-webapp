<?php
namespace App\Core;

use PDO;
use PDOStatement;
use PDOException;

class Database {

    private PDO $pdo;
    private ?PDOStatement $stmt = null;

    /**
     * Positional ctor. Any null falls back to Env, then default.
     *
     * @param string|null $host
     * @param string|null $name
     * @param string|null $user
     * @param string|null $pass
     * @param int|null    $port
     * @param string|null $charset
     */
    public function __construct(
        ?string $host    = null,
        ?string $name    = null,
        ?string $user    = null,
        ?string $pass    = null,
        ?int    $port    = null,
        ?string $charset = null
    ) {
        $host    = $host    ?? Env::get('DB_HOST', '127.0.0.1');
        $port    = $port    ?? Env::int('DB_PORT', 3306);
        $name    = $name    ?? Env::get('DB_NAME', 'events');
        $user    = $user    ?? Env::get('DB_USER', 'root');
        $pass    = $pass    ?? Env::get('DB_PASS', '');
        $charset = $charset ?? Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $opts);
        } catch (PDOException $e) {
            throw new \RuntimeException('PDO connect error: '.$e->getMessage(), 0, $e);
        }
    }

    /** Prepare a SQL statement (supports named :params or ? placeholders). */
    public function query(string $sql): self
    {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    /** Bind a single param; $param example: ':id' or 1 (for positional). */
    public function bind(string|int $param, mixed $value, ?int $type = null): self
    {
        if (!$this->stmt) { throw new \LogicException('Call query() before bind().'); }
        if ($type === null) {
            $type = match (true) {
                is_int($value)   => PDO::PARAM_INT,
                is_bool($value)  => PDO::PARAM_BOOL,
                is_null($value)  => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    /** Bind many at once: ['id'=>123, 'name'=>'Alice'] or [1=>123,2=>'x'] */
    public function bindAll(array $params): self
    {
        foreach ($params as $k => $v) {
            $this->bind(is_int($k) ? $k : (str_starts_with($k, ':') ? $k : (':'.$k)), $v);
        }
        return $this;
    }

    /** Execute current statement. */
    public function execute(): bool
    {
        if (!$this->stmt) { throw new \LogicException('Nothing to execute. Call query() first.'); }
        return $this->stmt->execute();
    }

    /** Fetch all rows (executes if not executed yet). */
    public function results(): array
    {
        $this->ensureExecuted();
        return $this->stmt->fetchAll();
    }

    /** Fetch a single row (or null). */
    public function single(): ?array
    {
        $this->ensureExecuted();
        $row = $this->stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch first column of first row (or null). */
    public function value(): mixed
    {
        $this->ensureExecuted();
        $v = $this->stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    public function rowCount(): int
    {
        if (!$this->stmt) { return 0; }
        return $this->stmt->rowCount();
    }

    public function lastId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    // Transactions
    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    private function ensureExecuted(): void
    {
        if (!$this->stmt) { throw new \LogicException('No statement. Call query() first.'); }
        try { $this->stmt->execute(); } catch (\PDOException) { /* already executed or error bubbles */ }
    }
}