<?php

declare(strict_types=1);

namespace App\Storage\Redis;

use Predis\Client;
use Predis\ClientInterface;
use Throwable;

/**
 * Redis cache / counters / latest values.
 *
 * The client is lazy: the connection is only established on the first command,
 * so the application still boots when Redis is temporarily unavailable.
 */
final class RedisClient
{
    private ?ClientInterface $client = null;

    public function __construct(private readonly array $config)
    {
    }

    public function client(): ClientInterface
    {
        if ($this->client instanceof ClientInterface) {
            return $this->client;
        }

        $parameters = [
            'scheme' => 'tcp',
            'host' => (string) $this->config['host'],
            'port' => (int) $this->config['port'],
            'database' => (int) $this->config['database'],
            'timeout' => 5.0,
            'read_write_timeout' => 5.0,
        ];

        if ((string) $this->config['password'] !== '') {
            $parameters['password'] = (string) $this->config['password'];
        }

        $this->client = new Client($parameters, [
            'prefix' => (string) $this->config['prefix'],
        ]);

        return $this->client;
    }

    public function get(string $key): ?string
    {
        try {
            $value = $this->client()->get($key);

            return $value === null ? null : (string) $value;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, string $value, ?int $ttl = null): bool
    {
        try {
            if ($ttl === null) {
                return (bool) $this->client()->set($key, $value);
            }

            return (bool) $this->client()->setex($key, $ttl, $value);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    public function hash(string $key): array
    {
        try {
            $values = $this->client()->hgetall($key);

            return is_array($values) ? $values : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function hashSet(string $key, string $field, string $value): bool
    {
        try {
            return (bool) $this->client()->hset($key, $field, $value);
        } catch (Throwable) {
            return false;
        }
    }

    public function incrementBy(string $key, float $amount): float
    {
        try {
            return (float) $this->client()->incrbyfloat($key, $amount);
        } catch (Throwable) {
            return 0.0;
        }
    }

    public function push(string $key, string $value): bool
    {
        try {
            return (bool) $this->client()->lpush($key, $value);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function listSlice(string $key, int $start, int $stop): array
    {
        try {
            $values = $this->client()->lrange($key, $start, $stop);

            return is_array($values) ? $values : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function trim(string $key, int $start, int $stop): bool
    {
        try {
            return (bool) $this->client()->ltrim($key, $start, $stop);
        } catch (Throwable) {
            return false;
        }
    }

    public function expire(string $key, int $ttl): bool
    {
        try {
            return (bool) $this->client()->expire($key, $ttl);
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return (bool) $this->client()->del($key);
        } catch (Throwable) {
            return false;
        }
    }

    public function ping(): bool
    {
        try {
            return strtoupper((string) $this->client()->ping()) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }
}
