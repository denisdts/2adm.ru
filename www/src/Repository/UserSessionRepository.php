<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\MySql\MySqlConnection;

/**
 * Opaque bearer sessions for dashboard users.
 *
 * Only the SHA-256 hash of a token is stored, so a database leak does not
 * expose usable session tokens.
 */
final class UserSessionRepository
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    public function create(
        int $userId,
        string $tokenHash,
        int $ttlSeconds,
        ?string $userAgent,
        ?string $ip,
    ): void {
        $ttl = max(60, $ttlSeconds);

        $this->db->execute(
            sprintf(
                'INSERT INTO user_sessions (user_id, token_hash, expires_at, user_agent, ip)
                 VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL %d SECOND), :user_agent, :ip)',
                $ttl,
            ),
            [
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'user_agent' => $userAgent,
                'ip' => $ip,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActive(string $tokenHash): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.id, u.email, u.username, u.name, u.company_id,
                    c.name AS company_name, u.group_id, g.code AS group_code
               FROM user_sessions s
               JOIN users u ON u.id = s.user_id
               LEFT JOIN companies c ON c.id = u.company_id
               LEFT JOIN user_groups g ON g.id = u.group_id
              WHERE s.token_hash = :token_hash
                AND s.expires_at > NOW()
              LIMIT 1',
            ['token_hash' => $tokenHash],
        );
    }

    public function touch(string $tokenHash): void
    {
        $this->db->execute(
            'UPDATE user_sessions SET last_used_at = NOW() WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );
    }

    public function delete(string $tokenHash): void
    {
        $this->db->execute(
            'DELETE FROM user_sessions WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );
    }

    public function deleteExpiredForUser(int $userId): void
    {
        $this->db->execute(
            'DELETE FROM user_sessions WHERE user_id = :user_id AND expires_at < NOW()',
            ['user_id' => $userId],
        );
    }
}
