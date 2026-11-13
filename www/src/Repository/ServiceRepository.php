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
        return $this->db->fetchAll('SELECT id, slug, name, created_at FROM services ORDER BY slug');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, slug, name, created_at FROM services WHERE slug = :slug',
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
