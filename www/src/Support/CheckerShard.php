<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rendezvous (highest-random-weight) hashing for distributing services
 * between checker instances.
 *
 * The owner of a service is the alive checker with the highest
 * hash(checker GUID + service slug). When a checker joins or leaves, only
 * the services it owned move; the rest keep their previous owner.
 */
final class CheckerShard
{
    /**
     * @param list<array{id: int, guid: string}> $checkers
     */
    public static function owner(string $slug, array $checkers): ?int
    {
        $owner = null;
        $best = null;

        foreach ($checkers as $checker) {
            $guid = (string) ($checker['guid'] ?? '');

            if ($guid === '') {
                continue;
            }

            $weight = hash('sha256', $guid . ':' . $slug, true);

            if ($best === null || strcmp($weight, $best) > 0) {
                $best = $weight;
                $owner = (int) $checker['id'];
            }
        }

        return $owner;
    }

    /**
     * @param list<array<string, mixed>> $services
     * @param list<array{id: int, guid: string}> $checkers
     * @return list<array<string, mixed>>
     */
    public static function filter(array $services, array $checkers, int $checkerId): array
    {
        if ($checkers === []) {
            return [];
        }

        $assigned = [];

        foreach ($services as $service) {
            $slug = (string) ($service['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            if (self::owner($slug, $checkers) === $checkerId) {
                $assigned[] = $service;
            }
        }

        return $assigned;
    }
}
