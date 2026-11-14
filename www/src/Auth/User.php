<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Authenticated dashboard user.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
        public readonly ?int $companyId = null,
        public readonly ?string $companyName = null,
        public readonly ?string $username = null,
        public readonly string $groupCode = 'clients',
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['name'],
            isset($row['company_id']) && $row['company_id'] !== null ? (int) $row['company_id'] : null,
            isset($row['company_name']) && $row['company_name'] !== null ? (string) $row['company_name'] : null,
            isset($row['username']) && $row['username'] !== null ? (string) $row['username'] : null,
            isset($row['group_code']) && $row['group_code'] !== null ? (string) $row['group_code'] : 'clients',
        );
    }

    public function isAdministrator(): bool
    {
        return $this->groupCode === 'administrators';
    }

    /**
     * @return array{id: int, email: string, name: string, username: string|null, group: string, company_id: int|null, company_name: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'username' => $this->username,
            'group' => $this->groupCode,
            'company_id' => $this->companyId,
            'company_name' => $this->companyName,
        ];
    }
}
