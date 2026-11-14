<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

/**
 * Application configuration, built from environment variables.
 *
 * The very same variables are used for the local stack (mysql / clickhouse /
 * redis running inside the project) and for production (external servers), so
 * switching between them is only a matter of changing the host names.
 */
final class Config
{
    private function __construct(private readonly array $items)
    {
    }

    public static function fromEnv(): self
    {
        return new self([
            'app' => [
                'name' => Env::get('APP_NAME', 'Monitoring'),
                'env' => Env::get('APP_ENV', 'local'),
                'debug' => Env::bool('APP_DEBUG', false),
                'key' => Env::get('APP_KEY', 'dev-only-change-me'),
                'port' => Env::int('APP_PORT', 7060),
                'trusted_proxies' => Env::list('APP_TRUSTED_PROXIES'),
            ],
            'api' => [
                'key_header' => Env::get('API_KEY_HEADER', 'X-Api-Key'),
                'require_read_auth' => Env::bool('API_REQUIRE_READ_AUTH', false),
                'rate_limit_per_minute' => Env::int('API_RATE_LIMIT_PER_MINUTE', 600),
                'admin_key' => Env::get('API_ADMIN_KEY'),
            ],
            'mysql' => [
                'host' => Env::get('DB_HOST', 'mysql'),
                'port' => Env::int('DB_PORT', 3306),
                'database' => Env::get('DB_DATABASE', 'monitoring'),
                'username' => Env::get('DB_USERNAME', 'monitoring'),
                'password' => Env::get('DB_PASSWORD', ''),
            ],
            'clickhouse' => [
                'host' => Env::get('CLICKHOUSE_HOST', 'clickhouse'),
                'port' => Env::int('CLICKHOUSE_PORT', 8123),
                'database' => Env::get('CLICKHOUSE_DATABASE', 'monitoring'),
                'username' => Env::get('CLICKHOUSE_USERNAME', 'monitoring'),
                'password' => Env::get('CLICKHOUSE_PASSWORD', ''),
                'timeout' => Env::int('CLICKHOUSE_TIMEOUT', 15),
            ],
            'redis' => [
                'host' => Env::get('REDIS_HOST', 'redis'),
                'port' => Env::int('REDIS_PORT', 6379),
                'password' => Env::get('REDIS_PASSWORD', ''),
                'database' => Env::int('REDIS_DATABASE', 0),
                'prefix' => Env::get('REDIS_PREFIX', 'mon:'),
            ],
            'monitoring' => [
                'retention_days' => Env::int('METRICS_RETENTION_DAYS', 90),
                'default_window' => Env::int('METRICS_DEFAULT_WINDOW', 3600),
            ],
            'auth' => [
                'session_ttl_days' => Env::int('AUTH_SESSION_TTL_DAYS', 30),
                'login_max_attempts' => Env::int('AUTH_LOGIN_MAX_ATTEMPTS', 10),
                'register_max_attempts' => Env::int('AUTH_REGISTER_MAX_ATTEMPTS', 5),
                'login_window_seconds' => Env::int('AUTH_LOGIN_WINDOW', 300),
            ],
        ]);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function isProduction(): bool
    {
        return in_array(strtolower((string) $this->get('app.env')), ['prod', 'production'], true);
    }

    public function all(): array
    {
        return $this->items;
    }
}
