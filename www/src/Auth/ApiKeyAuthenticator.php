<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Config;
use App\Repository\ApiKeyRepository;
use App\Storage\Redis\RedisClient;
use App\Http\Request;

/**
 * Validates the "X-Api-Key" header.
 *
 * Key format: mk_<prefix>_<secret>
 *   - prefix  : 8 hex characters, used to find the key in the database
 *   - secret  : random value, stored as an HMAC-SHA256 hash
 *
 * Successful lookups are cached in Redis for a short period of time.
 */
final class ApiKeyAuthenticator
{
    private const CACHE_TTL = 60;
    private const TOUCH_INTERVAL = 60;

    public function __construct(
        private readonly Config $config,
        private readonly ApiKeyRepository $keys,
        private readonly RedisClient $redis,
    ) {
    }

    public function authenticate(Request $request): ?ApiKey
    {
        $raw = $request->header((string) $this->config->get('api.key_header'));

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $parsed = $this->parse(trim($raw));

        if ($parsed === null) {
            return null;
        }

        [$prefix, $secret] = $parsed;

        $secretHash = $this->hash($secret);

        $cached = $this->fromCache($prefix, $secretHash);
        if ($cached instanceof ApiKey) {
            return $cached;
        }

        $record = $this->keys->findByPrefix($prefix);

        if ($record === null) {
            return null;
        }

        if (!hash_equals((string) $record['secret_hash'], $secretHash)) {
            return null;
        }

        $key = $this->toApiKey($record);
        $this->toCache($prefix, $secretHash, $key);
        $this->touch($prefix);

        return $key;
    }

    public function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) $this->config->get('app.key'));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parse(string $raw): ?array
    {
        $parts = explode('_', $raw);

        if (count($parts) < 3) {
            return null;
        }

        $prefix = $parts[1];
        $secret = implode('_', array_slice($parts, 2));

        if (!preg_match('/^[a-f0-9]{8,32}$/', $prefix) || strlen($secret) < 16) {
            return null;
        }

        return [$prefix, $secret];
    }

    private function toApiKey(array $record): ApiKey
    {
        return new ApiKey(
            (int) $record['id'],
            (string) $record['prefix'],
            (string) $record['scope'],
            $record['service_id'] === null ? null : (int) $record['service_id'],
            $record['service_slug'] === null ? null : (string) $record['service_slug'],
            (string) $record['name'],
        );
    }

    private function fromCache(string $prefix, string $secretHash): ?ApiKey
    {
        $cached = $this->redis->get('apikey:' . $prefix);

        if ($cached === null) {
            return null;
        }

        $decoded = json_decode($cached, true);

        if (!is_array($decoded)) {
            return null;
        }

        if (!hash_equals((string) ($decoded['secret_hash'] ?? ''), $secretHash)) {
            return null;
        }

        return new ApiKey(
            (int) ($decoded['id'] ?? 0),
            (string) ($decoded['prefix'] ?? $prefix),
            (string) ($decoded['scope'] ?? 'global'),
            isset($decoded['service_id']) ? (int) $decoded['service_id'] : null,
            $decoded['service_slug'] ?? null,
            (string) ($decoded['name'] ?? ''),
        );
    }

    private function toCache(string $prefix, string $secretHash, ApiKey $key): void
    {
        $this->redis->set('apikey:' . $prefix, (string) json_encode([
            'id' => $key->id,
            'prefix' => $key->prefix,
            'secret_hash' => $secretHash,
            'scope' => $key->scope,
            'service_id' => $key->serviceId,
            'service_slug' => $key->serviceSlug,
            'name' => $key->name,
        ]), self::CACHE_TTL);
    }

    /**
     * Keeps "last_used_at" roughly up to date without hammering the database.
     */
    private function touch(string $prefix): void
    {
        $lock = 'apikey:used:' . $prefix;

        if (!$this->redis->set($lock, '1', self::TOUCH_INTERVAL)) {
            return;
        }

        $this->keys->touch($prefix);
    }
}
