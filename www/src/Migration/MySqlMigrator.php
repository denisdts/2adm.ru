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

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                name VARCHAR(128) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                last_login_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                last_used_at DATETIME NULL DEFAULT NULL,
                user_agent VARCHAR(255) NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_sessions_token (token_hash),
                KEY idx_user_sessions_user (user_id),
                KEY idx_user_sessions_expires (expires_at),
                CONSTRAINT fk_user_sessions_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS companies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(128) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_groups (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_groups_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->db->pdo()->exec(
            "INSERT IGNORE INTO user_groups (code, name)
             VALUES ('administrators', 'Administrators'), ('clients', 'Clients')",
        );

        $adminGroupId = (int) $this->db->fetchValue("SELECT id FROM user_groups WHERE code = 'administrators'");
        $clientsGroupId = (int) $this->db->fetchValue("SELECT id FROM user_groups WHERE code = 'clients'");

        $this->addColumn('users', 'username', 'VARCHAR(64) NULL');
        $this->addColumn('users', 'group_id', 'INT UNSIGNED NULL');

        if (!$this->indexExists('users', 'uniq_users_username')) {
            $this->db->pdo()->exec('ALTER TABLE users ADD UNIQUE KEY uniq_users_username (username)');
        }

        if (!$this->indexExists('users', 'idx_users_group')) {
            $this->db->pdo()->exec('ALTER TABLE users ADD KEY idx_users_group (group_id)');
        }

        if (!$this->constraintExists('users', 'fk_users_group')) {
            $this->db->pdo()->exec(
                'ALTER TABLE users ADD CONSTRAINT fk_users_group
                    FOREIGN KEY (group_id) REFERENCES user_groups (id) ON DELETE SET NULL',
            );
        }

        $this->db->execute(
            'UPDATE users SET group_id = :group_id WHERE group_id IS NULL',
            ['group_id' => $clientsGroupId],
        );

        $adminCount = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM users WHERE username = 'admin' OR email = 'admin@2adm.ru'",
        );

        if ($adminCount === 0) {
            $this->db->insert('users', [
                'email' => 'admin@2adm.ru',
                'username' => 'admin',
                'name' => 'Administrator',
                'password_hash' => password_hash('Ui2reer1asheeph2', PASSWORD_DEFAULT),
                'group_id' => $adminGroupId,
            ]);
        }

        $this->db->execute(
            "UPDATE users SET group_id = :group_id WHERE username = 'admin' OR email = 'admin@2adm.ru'",
            ['group_id' => $adminGroupId],
        );

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS checkers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(128) NOT NULL,
                host VARCHAR(190) NULL,
                version VARCHAR(32) NULL,
                api_key_id INT UNSIGNED NULL,
                last_seen_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_checkers_name (name),
                KEY idx_checkers_api_key (api_key_id),
                CONSTRAINT fk_checkers_api_key
                    FOREIGN KEY (api_key_id) REFERENCES api_keys (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->db->pdo()->exec('ALTER TABLE checkers MODIFY COLUMN last_seen_at DATETIME NULL');
        $this->addColumn('checkers', 'bash_command', 'TEXT NULL');
        $this->addColumn('checkers', 'guid', 'CHAR(36) NULL AFTER id');
        $this->addColumn('checkers', 'ip', 'VARCHAR(45) NULL AFTER guid');

        if (!$this->indexExists('checkers', 'uniq_checkers_guid')) {
            $this->db->pdo()->exec('ALTER TABLE checkers ADD UNIQUE KEY uniq_checkers_guid (guid)');
        }

        $this->addColumn('services', 'last_checker_id', 'INT UNSIGNED NULL');
        $this->addColumn('services', 'last_checked_at', 'DATETIME NULL');

        if (!$this->indexExists('services', 'idx_services_last_checker')) {
            $this->db->pdo()->exec('ALTER TABLE services ADD KEY idx_services_last_checker (last_checker_id)');
        }

        if (!$this->constraintExists('services', 'fk_services_last_checker')) {
            $this->db->pdo()->exec(
                'ALTER TABLE services ADD CONSTRAINT fk_services_last_checker
                    FOREIGN KEY (last_checker_id) REFERENCES checkers (id) ON DELETE SET NULL',
            );
        }

        $this->db->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NULL,
                company_id INT UNSIGNED NULL,
                site_id INT UNSIGNED NULL,
                type VARCHAR(64) NOT NULL,
                message VARCHAR(512) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'new',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_notifications_created (created_at),
                KEY idx_notifications_company (company_id),
                CONSTRAINT fk_notifications_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_notifications_company
                    FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL,
                CONSTRAINT fk_notifications_site
                    FOREIGN KEY (site_id) REFERENCES services (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        if (!$this->columnExists('services', 'company_id')) {
            $this->db->pdo()->exec('ALTER TABLE services ADD COLUMN company_id INT UNSIGNED NULL AFTER name');
        }

        if (!$this->indexExists('services', 'idx_services_company')) {
            $this->db->pdo()->exec('ALTER TABLE services ADD KEY idx_services_company (company_id)');
        }

        if (!$this->constraintExists('services', 'fk_services_company')) {
            $this->db->pdo()->exec(
                'ALTER TABLE services ADD CONSTRAINT fk_services_company
                    FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL',
            );
        }

        $this->addColumn('services', 'domain_registered_at', 'DATETIME NULL');
        $this->addColumn('services', 'domain_expires_at', 'DATETIME NULL');
        $this->addColumn('services', 'ssl_has_certificate', 'TINYINT(1) NULL');
        $this->addColumn('services', 'ssl_valid_from', 'DATETIME NULL');
        $this->addColumn('services', 'ssl_valid_to', 'DATETIME NULL');

        if (!$this->columnExists('users', 'company_id')) {
            $this->db->pdo()->exec('ALTER TABLE users ADD COLUMN company_id INT UNSIGNED NULL AFTER name');
        }

        if (!$this->indexExists('users', 'idx_users_company')) {
            $this->db->pdo()->exec('ALTER TABLE users ADD KEY idx_users_company (company_id)');
        }

        if (!$this->constraintExists('users', 'fk_users_company')) {
            $this->db->pdo()->exec(
                'ALTER TABLE users ADD CONSTRAINT fk_users_company
                    FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL',
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column',
            ['table' => $table, 'column' => $column],
        ) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND INDEX_NAME = :index',
            ['table' => $table, 'index' => $index],
        ) > 0;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND CONSTRAINT_NAME = :constraint',
            ['table' => $table, 'constraint' => $constraint],
        ) > 0;
    }

    private function addColumn(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->pdo()->exec(sprintf(
                'ALTER TABLE %s ADD COLUMN %s %s',
                $table,
                $column,
                $definition,
            ));
        }
    }
}
