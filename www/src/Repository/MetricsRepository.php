<?php

declare(strict_types=1);

namespace App\Repository;

use App\Storage\ClickHouse\ClickHouseClient;

/**
 * Time series access (ClickHouse).
 */
final class MetricsRepository
{
    public function __construct(private readonly ClickHouseClient $clickhouse)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function insert(array $rows): int
    {
        return $this->clickhouse->insert('metrics', $rows);
    }

    /**
     * @return list<array{ts: string, value: float}>
     */
    public function series(
        string $service,
        ?string $name,
        int $from,
        int $to,
        int $step,
        int $limit,
    ): array {
        $params = [
            'service' => $service,
            'from' => $from,
            'to' => $to,
        ];

        $where = 'service = {service:String}
              AND ts >= toDateTime64({from:UInt32}, 3, \'UTC\')
              AND ts < toDateTime64({to:UInt32}, 3, \'UTC\')';

        if ($name !== null) {
            $where .= ' AND name = {name:String}';
            $params['name'] = $name;
        }

        if ($step > 0) {
            $sql = sprintf(
                'SELECT toStartOfInterval(ts, INTERVAL %d SECOND) AS ts, avg(value) AS value
                   FROM metrics
                  WHERE %s
                  GROUP BY ts
                  ORDER BY ts ASC
                  LIMIT %d',
                $step,
                $where,
                $limit,
            );
        } else {
            $sql = sprintf(
                'SELECT ts, value
                   FROM metrics
                  WHERE %s
                  ORDER BY ts ASC
                  LIMIT %d',
                $where,
                $limit,
            );
        }

        $rows = $this->clickhouse->query($sql, $params);

        $points = [];
        foreach ($rows as $row) {
            $points[] = [
                'ts' => (string) ($row['ts'] ?? ''),
                'value' => (float) ($row['value'] ?? 0.0),
            ];
        }

        return $points;
    }

    public function count(string $service): int
    {
        $rows = $this->clickhouse->query(
            'SELECT count() AS total FROM metrics WHERE service = {service:String}',
            ['service' => $service],
        );

        return (int) ($rows[0]['total'] ?? 0);
    }

    /**
     * Recent raw points for a service, newest first.
     *
     * @return list<array{name: string, kind: string, value: float, ts: string}>
     */
    public function history(string $service, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $rows = $this->clickhouse->query(
            sprintf(
                'SELECT name, kind, value, ts
                   FROM metrics
                  WHERE service = {service:String}
                  ORDER BY ts DESC
                  LIMIT %d',
                $limit,
            ),
            ['service' => $service],
        );

        $points = [];

        foreach ($rows as $row) {
            $points[] = [
                'name' => (string) ($row['name'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'gauge'),
                'value' => (float) ($row['value'] ?? 0.0),
                'ts' => (string) ($row['ts'] ?? ''),
            ];
        }

        return $points;
    }
}
