<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\MySql\MySqlConnection;
use RuntimeException;

final class CheckerRepository
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    public function heartbeat(
        string $guid,
        string $name,
        ?string $host,
        ?string $version,
        ?string $ip,
        ?int $apiKeyId,
    ): int {
        $this->db->execute(
            'INSERT INTO checkers (guid, name, host, version, ip, api_key_id, last_seen_at)
             VALUES (:guid, :name, :host, :version, :ip, :api_key_id, NOW()) AS new
             ON DUPLICATE KEY UPDATE
                guid = IF(checkers.guid IS NULL, new.guid, checkers.guid),
                host = new.host,
                version = new.version,
                ip = new.ip,
                api_key_id = new.api_key_id,
                last_seen_at = NOW()',
            [
                'guid' => $guid,
                'name' => $name,
                'host' => $host,
                'version' => $version,
                'ip' => $ip,
                'api_key_id' => $apiKeyId,
            ],
        );

        $id = $this->findIdByGuid($guid) ?? $this->findIdByName($name);

        if ($id === null) {
            throw new RuntimeException('Could not register checker.');
        }

        return $id;
    }

    /**
     * Records that a known checker has just called the API.
     *
     * Returns the checker id, or null when the guid is unknown (the checker
     * has not sent a heartbeat yet).
     */
    public function touch(string $guid, ?int $apiKeyId, ?string $ip = null): ?int
    {
        $id = $this->findIdByGuid($guid);

        if ($id === null) {
            return null;
        }

        $this->db->execute(
            'UPDATE checkers
                SET last_seen_at = NOW(),
                    ip = COALESCE(:ip, ip),
                    api_key_id = COALESCE(:api_key_id, api_key_id)
              WHERE id = :id',
            [
                'ip' => $ip,
                'api_key_id' => $apiKeyId,
                'id' => $id,
            ],
        );

        return $id;
    }

    /**
     * Public checker directory for the dashboard: no GUID, IP or API key data.
     *
     * @return list<array{id: int, name: string, version: ?string, last_seen_at: ?string}>
     */
    public function allPublic(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, version, last_seen_at
               FROM checkers
              ORDER BY name, id',
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'version' => $row['version'] === null ? null : (string) $row['version'],
            'last_seen_at' => $row['last_seen_at'] === null ? null : (string) $row['last_seen_at'],
        ], $rows);
    }

    /**
     * Checkers that sent a heartbeat within the last $windowSeconds seconds.
     * The current checker is always included, even if its heartbeat is older.
     *
     * @return list<array{id: int, guid: string, name: string, last_seen_at: ?string}>
     */
    public function alive(int $windowSeconds, ?int $includeId = null): array
    {
        $windowSeconds = max(1, $windowSeconds);

        $condition = sprintf('last_seen_at >= DATE_SUB(NOW(), INTERVAL %d SECOND)', $windowSeconds);
        $params = [];

        if ($includeId !== null) {
            $condition = sprintf('(%s OR id = :include_id)', $condition);
            $params['include_id'] = $includeId;
        }

        $rows = $this->db->fetchAll(
            'SELECT id, guid, name, last_seen_at
               FROM checkers
              WHERE guid IS NOT NULL
                AND guid <> \'\'
                AND ' . $condition . '
              ORDER BY id',
            $params,
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'guid' => (string) $row['guid'],
            'name' => (string) $row['name'],
            'last_seen_at' => $row['last_seen_at'] === null ? null : (string) $row['last_seen_at'],
        ], $rows);
    }

    public function findIdByGuid(string $guid): ?int
    {
        $value = $this->db->fetchValue(
            'SELECT id FROM checkers WHERE guid = :guid LIMIT 1',
            ['guid' => $guid],
        );

        return $value === null ? null : (int) $value;
    }

    public function findIdByName(string $name): ?int
    {
        $value = $this->db->fetchValue(
            'SELECT id FROM checkers WHERE name = :name LIMIT 1',
            ['name' => $name],
        );

        return $value === null ? null : (int) $value;
    }
}
