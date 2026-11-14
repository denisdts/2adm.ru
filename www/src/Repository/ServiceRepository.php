<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\MySql\MySqlConnection;

final class ServiceRepository
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT id, slug, name, created_at,
                    domain_registered_at, domain_expires_at,
                    ssl_has_certificate, ssl_valid_from, ssl_valid_to
               FROM services
              ORDER BY slug',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForCompany(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT id, slug, name, company_id, domain_registered_at, domain_expires_at, ssl_has_certificate, ssl_valid_from, ssl_valid_to, created_at
               FROM services
              WHERE company_id = :company_id
              ORDER BY slug',
            ['company_id' => $companyId],
        );
    }

    public function createForCompany(string $slug, string $name, int $companyId): int
    {
        return $this->db->insert('services', [
            'slug' => $slug,
            'name' => $name,
            'company_id' => $companyId,
        ]);
    }

    public function updateSslMetadata(
        string $slug,
        bool $hasCertificate,
        ?string $validFrom,
        ?string $validTo,
        ?int $checkerId = null,
    ): void {
        $params = [
            'has_certificate' => $hasCertificate ? 1 : 0,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'slug' => $slug,
        ];

        $checker = '';

        if ($checkerId !== null) {
            $checker = ',
                    last_checker_id = :checker_id,
                    last_checked_at = NOW()';
            $params['checker_id'] = $checkerId;
        }

        $this->db->execute(
            'UPDATE services
                SET ssl_has_certificate = :has_certificate,
                    ssl_valid_from = :valid_from,
                    ssl_valid_to = :valid_to' . $checker . '
              WHERE slug = :slug',
            $params,
        );
    }

    public function updateDomainMetadata(
        string $slug,
        ?string $registeredAt,
        ?string $expiresAt,
        ?int $checkerId = null,
    ): void {
        $params = [
            'registered_at' => $registeredAt,
            'expires_at' => $expiresAt,
            'slug' => $slug,
        ];

        $checker = '';

        if ($checkerId !== null) {
            $checker = ',
                    last_checker_id = :checker_id,
                    last_checked_at = NOW()';
            $params['checker_id'] = $checkerId;
        }

        $this->db->execute(
            'UPDATE services
                SET domain_registered_at = :registered_at,
                    domain_expires_at = :expires_at' . $checker . '
              WHERE slug = :slug',
            $params,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, slug, name, company_id, domain_registered_at, domain_expires_at, ssl_has_certificate, ssl_valid_from, ssl_valid_to, created_at
               FROM services
              WHERE slug = :slug',
            ['slug' => $slug],
        );
    }

    public function create(string $slug, string $name): int
    {
        return $this->db->insert('services', [
            'slug' => $slug,
            'name' => $name,
        ]);
    }
}
