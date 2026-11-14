<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\MySql\MySqlConnection;
use RuntimeException;
use Throwable;

/**
 * Dashboard users and their companies.
 */
final class UserRepository
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.id, u.email, u.username, u.name, u.password_hash, u.company_id,
                    c.name AS company_name, u.group_id, g.code AS group_code
               FROM users u
               LEFT JOIN companies c ON c.id = u.company_id
               LEFT JOIN user_groups g ON g.id = u.group_id
              WHERE u.email = :email
              LIMIT 1',
            ['email' => $email],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByLogin(string $login): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.id, u.email, u.username, u.name, u.password_hash, u.company_id,
                    c.name AS company_name, u.group_id, g.code AS group_code
               FROM users u
               LEFT JOIN companies c ON c.id = u.company_id
               LEFT JOIN user_groups g ON g.id = u.group_id
              WHERE u.email = :email OR u.username = :username
              LIMIT 1',
            ['email' => $login, 'username' => $login],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.id, u.email, u.username, u.name, u.company_id,
                    c.name AS company_name, u.group_id, g.code AS group_code, u.created_at
               FROM users u
               LEFT JOIN companies c ON c.id = u.company_id
               LEFT JOIN user_groups g ON g.id = u.group_id
              WHERE u.id = :id
              LIMIT 1',
            ['id' => $id],
        );
    }

    public function usernameExists(string $username): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM users WHERE username = :username',
            ['username' => $username],
        ) > 0;
    }

    public function createAdministrator(
        string $email,
        string $username,
        string $name,
        string $passwordHash,
    ): int {
        $groupId = $this->groupIdByCode('administrators');

        if ($groupId === null) {
            throw new RuntimeException('The administrators group is missing.');
        }

        return $this->db->insert('users', [
            'email' => $email,
            'username' => $username,
            'name' => $name,
            'password_hash' => $passwordHash,
            'company_id' => null,
            'group_id' => $groupId,
        ]);
    }

    /**
     * Creates a company and its first user atomically.
     *
     * @return array{user_id: int, company_id: int}
     */
    public function createWithCompany(string $email, string $name, string $passwordHash, string $companyName): array
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            $companyId = $this->db->insert('companies', ['name' => $companyName]);
            $userId = $this->db->insert('users', [
                'email' => $email,
                'name' => $name,
                'password_hash' => $passwordHash,
                'company_id' => $companyId,
                'group_id' => $this->groupIdByCode('clients'),
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        return ['user_id' => $userId, 'company_id' => $companyId];
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            ['id' => $id],
        );
    }

    private function groupIdByCode(string $code): ?int
    {
        $value = $this->db->fetchValue(
            'SELECT id FROM user_groups WHERE code = :code',
            ['code' => $code],
        );

        return $value === null ? null : (int) $value;
    }
}
