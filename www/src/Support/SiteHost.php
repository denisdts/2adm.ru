<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates site addresses added through the dashboard.
 *
 * The checker performs outbound HTTP/TLS requests for every stored slug, so
 * accepting loopback, private or reserved addresses would turn the checker
 * into an SSRF proxy for the internal network.
 */
final class SiteHost
{
    private const BLOCKED_HOSTS = ['localhost'];

    private const BLOCKED_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.home.arpa',
        '.lan',
        '.localdomain',
        '.test',
        '.invalid',
        '.example',
    ];

    private const BLOCKED_CIDRS = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '240.0.0.0/4',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        '100::/64',
        '2001:2::/48',
        '2001:10::/28',
        '2001:20::/28',
        '::/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
    ];

    public static function fromUrl(string $value): ?string
    {
        if (preg_match('/\s/', $value) === 1) {
            return null;
        }

        $url = $value;

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) !== 1) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,63}$/', $host) !== 1) {
            return null;
        }

        return self::isPublicHost($host) ? $host : null;
    }

    public static function isPublicHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        if ($host === '' || in_array($host, self::BLOCKED_HOSTS, true)) {
            return false;
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // A single-label host is an internal machine name, not a public site.
        if (strpos($host, '.') === false) {
            return false;
        }

        $labels = explode('.', $host);
        $tld = (string) end($labels);

        // IP shorthands such as 127.1 or 2130706433 must not slip through as
        // hostnames. A numeric-only TLD is never a real public domain.
        if (preg_match('/^[0-9]+$/', $tld) === 1 || preg_match('/^[0-9.]+$/', $host) === 1) {
            return false;
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (is_array($records) && $records !== []) {
                foreach ($records as $record) {
                    $ip = $record['ip'] ?? $record['ipv6'] ?? null;

                    if (is_string($ip) && $ip !== '' && !self::isPublicIp($ip)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        $ip = trim($ip, '[]');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $packed = @inet_pton($ip);

        if (
            $packed !== false
            && strlen($packed) === 16
            && substr($packed, 0, 10) === str_repeat("\0", 10)
            && substr($packed, 10, 2) === "\xff\xff"
        ) {
            $mapped = @inet_ntop(substr($packed, 12));

            return is_string($mapped) ? self::isPublicIp($mapped) : false;
        }

        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($public === false) {
            return false;
        }

        foreach (self::BLOCKED_CIDRS as $cidr) {
            if (self::cidrContains($cidr, $ip)) {
                return false;
            }
        }

        return true;
    }

    private static function cidrContains(string $cidr, string $ip): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        if ($bits === null) {
            return false;
        }

        $subnetBin = @inet_pton($subnet);
        $ipBin = @inet_pton($ip);

        if ($subnetBin === false || $ipBin === false || strlen($subnetBin) !== strlen($ipBin)) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > strlen($subnetBin) * 8) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($subnetBin, 0, $fullBytes) !== substr($ipBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($subnetBin[$fullBytes]) & $mask) === (ord($ipBin[$fullBytes]) & $mask);
    }
}
