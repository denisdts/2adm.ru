<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Config;
use App\Http\Request;

/**
 * Resolves the client IP, honouring the configured trusted proxy chain.
 */
final class ClientIp
{
    public static function fromRequest(Config $config, Request $request): string
    {
        $proxies = $config->get('app.trusted_proxies', []);
        $proxies = is_array($proxies) ? array_values(array_map('strval', $proxies)) : [];

        return $request->clientIp($proxies);
    }
}
