<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Authenticated API key.
 */
final class ApiKey
{
    public function __construct(
        public readonly int $id,
        public readonly string $prefix,
        public readonly string $scope,
        public readonly ?int $serviceId,
        public readonly ?string $serviceSlug,
        public readonly string $name,
    ) {
    }

    public function isGlobal(): bool
    {
        return $this->scope === 'global';
    }

    public function canWriteFor(?string $serviceSlug): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        return $serviceSlug !== null && $serviceSlug === $this->serviceSlug;
    }
}
