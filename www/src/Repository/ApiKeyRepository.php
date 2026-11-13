<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\MySql\MySqlConnection;

/**
 * API keys live in MySQL; metric data itself lives in ClickHouse.
 */
final class ApiKeyRepository
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPrefix(string $prefix): ?array
    {
        return $this->db->fetchOne(
            'SELECT k.id, k.prefix, k.secret_hash, k.name, k.scope, k.service_id, s.slug AS service_slug
               FROM api_keys k
               LEFT JOIN services s ON s.id = k.service_id
              WHERE k.prefix = :prefix
                AND k.revoked_at IS NULL',
            ['prefix' => $prefix],
        );
    }

    public function touch(string $prefix): void
    {
        $this->db->execute(
            'UPDATE api_keys SET last_used_at = NOW() WHERE prefix = :prefix',
            ['prefix' => $prefix],
        );
    }

    public function create(
        string $name,
        string $prefix,
        string $secretHash,
        string $scope,
        ?int $serviceId,
    ): int {
        return $this->db->insert('api_keys', [
            'name' => $name,
            'prefix' => $prefix,
            'secret_hash' => $secretHash,
            'scope' => $scope,
            'service_id' => $serviceId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT k.id, k.prefix, k.name, k.scope, k.service_id, k.last_used_at, k.created_at, s.slug AS service_slug
               FROM api_keys k
               LEFT JOIN services s ON s.id = k.service_id
              WHERE k.revoked_at IS NULL
              ORDER BY k.id',
        );
    }
}
