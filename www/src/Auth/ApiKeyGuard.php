<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Config;
use App\Exception\ApiException;
use App\Http\Request;
use App\Storage\Redis\RedisClient;

/**
 * Authentication + per-key rate limiting.
 */
final class ApiKeyGuard
{
    public function __construct(
        private readonly ApiKeyAuthenticator $authenticator,
        private readonly RedisClient $redis,
        private readonly Config $config,
    ) {
    }

    public function require(Request $request): ApiKey
    {
        $key = $this->authenticator->authenticate($request);

        if ($key === null) {
            throw new ApiException(
                sprintf('A valid "%s" header is required.', (string) $this->config->get('api.key_header')),
                401,
                'unauthorized',
            );
        }

        $this->enforceRateLimit($key);

        return $key;
    }

    /**
     * Reads are public unless API_REQUIRE_READ_AUTH=true; when a key is present
     * anyway, its rate limit still applies.
     */
    public function forRead(Request $request): ?ApiKey
    {
        $key = $this->authenticator->authenticate($request);

        if ($key === null) {
            if ((bool) $this->config->get('api.require_read_auth', false)) {
                throw new ApiException(
                    sprintf('A valid "%s" header is required.', (string) $this->config->get('api.key_header')),
                    401,
                    'unauthorized',
                );
            }

            return null;
        }

        $this->enforceRateLimit($key);

        return $key;
    }

    private function enforceRateLimit(ApiKey $key): void
    {
        $limit = (int) $this->config->get('api.rate_limit_per_minute', 0);

        if ($limit <= 0) {
            return;
        }

        $bucket = 'ratelimit:' . $key->id . ':' . intdiv(time(), 60);
        $current = $this->redis->incrementBy($bucket, 1.0);

        if ($current <= 0.0) {
            // Redis is unavailable: fail open instead of rejecting traffic.
            return;
        }

        $this->redis->expire($bucket, 120);

        if ($current > $limit) {
            throw new ApiException(
                sprintf('Rate limit of %d requests per minute exceeded.', $limit),
                429,
                'rate_limited',
                ['limit' => $limit],
            );
        }
    }
}
