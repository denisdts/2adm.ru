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

    public function heartbeat(string $guid, string $name, ?string $host, ?string $version, ?int $apiKeyId): int
    {
        $this->db->execute(
            'INSERT INTO checkers (guid, name, host, version, api_key_id, last_seen_at)
             VALUES (:guid, :name, :host, :version, :api_key_id, NOW()) AS new
             ON DUPLICATE KEY UPDATE
                guid = IF(checkers.guid IS NULL, new.guid, checkers.guid),
                host = new.host,
                version = new.version,
                api_key_id = new.api_key_id,
                last_seen_at = NOW()',
            [
                'guid' => $guid,
                'name' => $name,
                'host' => $host,
                'version' => $version,
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
    public function touch(string $guid, ?int $apiKeyId): ?int
    {
        $id = $this->findIdByGuid($guid);

        if ($id === null) {
            return null;
        }

        $this->db->execute(
            'UPDATE checkers
                SET last_seen_at = NOW(),
                    api_key_id = COALESCE(:api_key_id, api_key_id)
              WHERE id = :id',
            [
                'api_key_id' => $apiKeyId,
                'id' => $id,
            ],
        );

        return $id;
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
