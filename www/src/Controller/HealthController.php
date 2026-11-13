<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Response;
use App\Storage\ClickHouse\ClickHouseClient;
use App\Storage\MySql\MySqlConnection;
use App\Storage\Redis\RedisClient;

final class HealthController
{
    public function __construct(
        private readonly MySqlConnection $mysql,
        private readonly ClickHouseClient $clickhouse,
        private readonly RedisClient $redis,
    ) {
    }

    /**
     * Liveness probe used by Docker: does not depend on any backend.
     */
    public function live(): Response
    {
        return Response::text("ok\n");
    }

    /**
     * Readiness probe: reports every backend separately.
     */
    public function ready(): Response
    {
        $checks = [
            'mysql' => $this->mysql->ping(),
            'clickhouse' => $this->clickhouse->ping(),
            'redis' => $this->redis->ping(),
        ];

        $healthy = !in_array(false, $checks, true);

        return Response::json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
