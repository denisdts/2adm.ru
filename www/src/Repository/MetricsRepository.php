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
     * @return list<array{ts: string, value: float, checker_id: int}>
     */
    public function series(
        string $service,
        ?string $name,
        int $from,
        int $to,
        int $step,
        int $limit,
        ?int $checkerId = null,
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

        if ($checkerId !== null) {
            $where .= ' AND checker_id = {checker_id:UInt32}';
            $params['checker_id'] = $checkerId;
        }

        if ($step > 0) {
            $sql = sprintf(
                'SELECT toStartOfInterval(ts, INTERVAL %d SECOND) AS ts, checker_id, avg(value) AS value
                   FROM metrics
                  WHERE %s
                  GROUP BY checker_id, ts
                  ORDER BY ts ASC, checker_id ASC
                  LIMIT %d',
                $step,
                $where,
                $limit,
            );
        } else {
            $sql = sprintf(
                'SELECT ts, checker_id, value
                   FROM metrics
                  WHERE %s
                  ORDER BY ts ASC, checker_id ASC
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
                'checker_id' => (int) ($row['checker_id'] ?? 0),
            ];
        }

        return $points;
    }

    public function count(string $service, ?int $checkerId = null): int
    {
        $params = ['service' => $service];
        $where = 'service = {service:String}';

        if ($checkerId !== null) {
            $where .= ' AND checker_id = {checker_id:UInt32}';
            $params['checker_id'] = $checkerId;
        }

        $rows = $this->clickhouse->query(
            sprintf('SELECT count() AS total FROM metrics WHERE %s', $where),
            $params,
        );

        return (int) ($rows[0]['total'] ?? 0);
    }

    /**
     * Recent raw points for a service, newest first.
     *
     * @return list<array{name: string, kind: string, value: float, checker_id: int, ts: string}>
     */
    public function history(string $service, int $limit = 100, ?int $checkerId = null): array
    {
        $limit = max(1, min(500, $limit));
        $params = ['service' => $service];
        $where = 'service = {service:String}';

        if ($checkerId !== null) {
            $where .= ' AND checker_id = {checker_id:UInt32}';
            $params['checker_id'] = $checkerId;
        }

        $rows = $this->clickhouse->query(
            sprintf(
                'SELECT name, kind, value, checker_id, ts
                   FROM metrics
                  WHERE %s
                  ORDER BY ts DESC
                  LIMIT %d',
                $where,
                $limit,
            ),
            $params,
        );

        $points = [];

        foreach ($rows as $row) {
            $points[] = [
                'name' => (string) ($row['name'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'gauge'),
                'value' => (float) ($row['value'] ?? 0.0),
                'checker_id' => (int) ($row['checker_id'] ?? 0),
                'ts' => (string) ($row['ts'] ?? ''),
            ];
        }

        return $points;
    }
}
