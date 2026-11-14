<?php

declare(strict_types=1);

namespace App\Storage\MySql;

use PDO;
use PDOException;

/**
 * Lazily connected MySQL / MariaDB connection.
 */
final class MySqlConnection
{
    private ?PDO $pdo = null;

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) $this->config['host'],
            (int) $this->config['port'],
            (string) $this->config['database'],
        );

        $this->pdo = new PDO(
            $dsn,
            (string) $this->config['username'],
            (string) $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );

        $timezone = (string) ($this->config['timezone'] ?? '+00:00');
        $this->pdo->exec('SET time_zone = ' . $this->pdo->quote($timezone));

        return $this->pdo;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);

        return $rows[0] ?? null;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);

        return $row === null ? null : reset($row);
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $this->execute($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :set_' . $column;
        }

        $bound = [];
        foreach ($data as $column => $value) {
            $bound['set_' . $column] = $value;
        }

        return $this->execute(
            sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $assignments), $where),
            $bound + $params,
        );
    }

    public function ping(): bool
    {
        try {
            $this->pdo()->query('SELECT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
