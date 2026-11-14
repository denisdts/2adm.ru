<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\MySql\MySqlConnection;

final class AdminRepository
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function users(): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.email, u.username, u.name, u.group_id,
                    g.code AS group_code, g.name AS group_name,
                    u.company_id, c.name AS company_name,
                    u.last_login_at, u.created_at
               FROM users u
               LEFT JOIN user_groups g ON g.id = u.group_id
               LEFT JOIN companies c ON c.id = u.company_id
              ORDER BY u.id',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companies(): array
    {
        return $this->db->fetchAll(
            'SELECT c.id, c.name, c.created_at,
                    (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS users_count,
                    (SELECT COUNT(*) FROM services s WHERE s.company_id = c.id) AS sites_count
               FROM companies c
              ORDER BY c.id',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sites(): array
    {
        return $this->db->fetchAll(
            'SELECT s.id, s.slug, s.name, s.company_id, c.name AS company_name,
                    s.domain_registered_at, s.domain_expires_at,
                    s.ssl_has_certificate, s.ssl_valid_from, s.ssl_valid_to,
                    s.created_at
               FROM services s
               LEFT JOIN companies c ON c.id = s.company_id
              ORDER BY s.slug',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checkers(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT k.id, k.guid, k.ip, k.name, k.host, k.version, k.api_key_id, k.bash_command,
                    a.name AS api_key_name, k.last_seen_at, k.created_at,
                    (k.last_seen_at IS NOT NULL AND k.last_seen_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS online
               FROM checkers k
               LEFT JOIN api_keys a ON a.id = k.api_key_id
              ORDER BY k.last_seen_at DESC, k.id ASC',
        );

        foreach ($rows as &$row) {
            $row['online'] = (bool) (int) ($row['online'] ?? 0);
        }

        unset($row);

        return $rows;
    }

    public function checkerNameExists(string $name): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM checkers WHERE name = :name',
            ['name' => $name],
        ) > 0;
    }

    public function checkerGuidExists(string $guid): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM checkers WHERE guid = :guid',
            ['guid' => $guid],
        ) > 0;
    }

    public function checkerNameExistsExcept(string $name, int $id): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM checkers WHERE name = :name AND id <> :id',
            ['name' => $name, 'id' => $id],
        ) > 0;
    }

    public function updateCheckerName(int $id, string $name): void
    {
        $this->db->execute(
            'UPDATE checkers SET name = :name WHERE id = :id',
            ['name' => $name, 'id' => $id],
        );
    }

    public function createChecker(string $name, ?string $bashCommand, string $guid): int
    {
        return $this->db->insert('checkers', [
            'guid' => $guid,
            'name' => $name,
            'bash_command' => $bashCommand,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function checker(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT k.id, k.guid, k.ip, k.name, k.host, k.version, k.api_key_id, k.bash_command,
                    a.name AS api_key_name, k.last_seen_at, k.created_at,
                    (k.last_seen_at IS NOT NULL AND k.last_seen_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS online
               FROM checkers k
               LEFT JOIN api_keys a ON a.id = k.api_key_id
              WHERE k.id = :id
              LIMIT 1',
            ['id' => $id],
        );

        if ($row !== null) {
            $row['online'] = (bool) (int) ($row['online'] ?? 0);
        }

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notifications(): array
    {
        return $this->db->fetchAll(
            'SELECT n.id, n.type, n.message, n.status, n.user_id,
                    u.email AS user_email, n.company_id, c.name AS company_name,
                    n.site_id, s.slug AS site_slug, n.created_at, n.read_at
               FROM notifications n
               LEFT JOIN users u ON u.id = n.user_id
               LEFT JOIN companies c ON c.id = n.company_id
               LEFT JOIN services s ON s.id = n.site_id
              ORDER BY n.created_at DESC, n.id DESC
              LIMIT 200',
        );
    }
}
