<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\ApiKeyGuard;
use App\Config\Config;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\CheckerRepository;
use App\Support\CheckerGuid;
use App\Support\ClientIp;

final class CheckerController
{
    public function __construct(
        private readonly ApiKeyGuard $guard,
        private readonly CheckerRepository $checkers,
        private readonly Config $config,
    ) {
    }

    public function heartbeat(Request $request): Response
    {
        $key = $this->guard->require($request);
        $payload = $request->json();
        $name = trim((string) ($payload['name'] ?? ''));
        $host = trim((string) ($payload['host'] ?? ''));
        $version = trim((string) ($payload['version'] ?? ''));

        if ($name === '' || strlen($name) > 128) {
            throw new ApiException('Field "name" is required.', 422, 'validation_failed');
        }

        $rawGuid = $payload['guid'] ?? null;

        $guid = CheckerGuid::fromRequest($request)
            ?? (is_string($rawGuid) ? CheckerGuid::normalize($rawGuid) : null)
            ?? CheckerGuid::generate();

        $ip = ClientIp::fromRequest($this->config, $request);

        $id = $this->checkers->heartbeat(
            $guid,
            $name,
            $host === '' ? null : mb_substr($host, 0, 190),
            $version === '' ? null : mb_substr($version, 0, 32),
            $ip === '' ? null : $ip,
            $key->id,
        );

        return Response::json([
            'ok' => true,
            'checker_id' => $id,
            'guid' => $guid,
        ])->withHeader('Cache-Control', 'no-store');
    }
}
