<?php

declare(strict_types=1);

namespace App\Migration;

use App\Config\Config;
use App\Storage\ClickHouse\ClickHouseClient;
use RuntimeException;

/**
 * ClickHouse schema: the raw metric store with a TTL based on the retention
 * setting. The database itself is created when missing, so the service can
 * point at a fresh external ClickHouse server in production.
 */
final class ClickHouseMigrator
{
    public function __construct(
        private readonly ClickHouseClient $clickhouse,
        private readonly Config $config,
    ) {
    }

    public function migrate(): void
    {
        $database = $this->clickhouse->database();

        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new RuntimeException(sprintf('Invalid ClickHouse database name "%s".', $database));
        }

        $this->clickhouse->command(
            sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database),
            [],
            false,
        );

        $retentionDays = max(1, (int) $this->config->get('monitoring.retention_days', 90));

        $this->clickhouse->command(sprintf(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `%1$s`.`metrics` (
                service LowCardinality(String),
                name LowCardinality(String),
                kind LowCardinality(String) DEFAULT 'gauge',
                value Float64,
                labels Map(String, String),
                checker_id UInt32 DEFAULT 0,
                ts DateTime64(3, 'UTC'),
                ingested_at DateTime64(3, 'UTC') DEFAULT now64(3)
            )
            ENGINE = MergeTree
            PARTITION BY toYYYYMM(ts)
            ORDER BY (service, name, ts)
            TTL ts + INTERVAL %2$d DAY DELETE
            SETTINGS index_granularity = 8192
            SQL,
            $database,
            $retentionDays,
        ));

        $this->clickhouse->command(sprintf(
            'ALTER TABLE `%s`.`metrics` ADD COLUMN IF NOT EXISTS checker_id UInt32 DEFAULT 0',
            $database,
        ));
    }
}
