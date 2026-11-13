<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Thin wrapper around the environment variables.
 */
final class Env
{
    public static function raw(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::raw($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key);

        return $value === null ? $default : (float) $value;
    }

    /**
     * @return list<string>
     */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

        return $items;
    }
}
