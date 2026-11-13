<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Immutable HTTP request.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly array $server,
        public readonly string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = '/' . trim($path, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === '/' ? '/' : rtrim($path, '/'),
            $_GET,
            self::headersFromServer($_SERVER),
            $_SERVER,
            (string) (file_get_contents('php://input') ?: ''),
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }

        $decoded = json_decode($this->body, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    public function queryParam(string $name, ?string $default = null): ?string
    {
        $value = $this->query[$name] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        return (string) $value;
    }

    /**
     * Client IP, taking the trusted proxy chain into account.
     */
    public function clientIp(array $trustedProxies = []): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');

        if ($trustedProxies === [] || !self::isTrusted($remote, $trustedProxies)) {
            return $remote;
        }

        $forwarded = $this->header('x-forwarded-for');

        if ($forwarded === null || $forwarded === '') {
            return $remote;
        }

        foreach (explode(',', $forwarded) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && !self::isTrusted($candidate, $trustedProxies)) {
                return $candidate;
            }
        }

        return $remote;
    }

    /**
     * @param list<string> $trustedProxies
     */
    private static function isTrusted(string $ip, array $trustedProxies): bool
    {
        if ($ip === '') {
            return false;
        }

        foreach ($trustedProxies as $proxy) {
            if ($proxy === $ip) {
                return true;
            }

            if (!str_contains($proxy, '/')) {
                continue;
            }

            $subnet = @inet_pton($proxy);
            $address = @inet_pton($ip);

            if ($subnet === false || $address === false) {
                continue;
            }

            $bits = (int) substr($proxy, strpos($proxy, '/') + 1);
            $mask = self::maskFromBits($bits, strlen($subnet));

            if ($mask === null || !self::sameFamily($address, $subnet)) {
                continue;
            }

            if (($address & $mask) === ($subnet & $mask)) {
                return true;
            }
        }

        return false;
    }

    private static function maskFromBits(int $bits, int $bytes): ?string
    {
        if ($bits < 0 || $bits > $bytes * 8) {
            return null;
        }

        $mask = str_repeat("\xFF", intdiv($bits, 8));
        $remainder = $bits % 8;

        if ($remainder > 0) {
            $mask .= chr((0xFF << (8 - $remainder)) & 0xFF);
        }

        return str_pad($mask, $bytes, "\x00");
    }

    private static function sameFamily(string $a, string $b): bool
    {
        return (str_contains($a, ':') && str_contains($b, ':'))
            || (!str_contains($a, ':') && !str_contains($b, ':'));
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
                continue;
            }

            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $key))] = (string) $value;
            }
        }

        return $headers;
    }
}
