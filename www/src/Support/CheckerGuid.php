<?php

declare(strict_types=1);

namespace App\Support;

use App\Exception\ApiException;
use App\Http\Request;

/**
 * Identity of a checker instance: a UUID sent in the "X-Checker-Guid" header.
 */
final class CheckerGuid
{
    public const HEADER = 'x-checker-guid';

    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function fromRequest(Request $request): ?string
    {
        return self::normalize($request->header(self::HEADER));
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $guid = strtolower(trim($value));

        if ($guid === '') {
            return null;
        }

        if (preg_match(self::PATTERN, $guid) !== 1) {
            throw new ApiException(
                'Header "X-Checker-Guid" must be a UUID.',
                422,
                'validation_failed',
            );
        }

        return $guid;
    }

    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
