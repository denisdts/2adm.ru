<?php

declare(strict_types=1);

namespace App\Migration;

use App\Storage\MySql\MySqlConnection;

/**
 * MySQL schema: services and API keys (metric data lives in ClickHouse).
 */
final class MySqlMigrator
{
    public function __construct(private readonly MySqlConnection $db)
    {
    }

    public function migrate(): void
    {
        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS services (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(64) NOT NULL,
                name VARCHAR(128) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_services_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                prefix CHAR(32) NOT NULL,
                secret_hash CHAR(64) NOT NULL,
                name VARCHAR(128) NOT NULL,
                scope ENUM('global', 'service') NOT NULL DEFAULT 'service',
                service_id INT UNSIGNED NULL,
                last_used_at TIMESTAMP NULL DEFAULT NULL,
                revoked_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_api_keys_prefix (prefix),
                KEY idx_api_keys_service (service_id),
                CONSTRAINT fk_api_keys_service
                    FOREIGN KEY (service_id) REFERENCES services (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
}
