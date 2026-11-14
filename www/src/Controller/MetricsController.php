<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\ApiKeyGuard;
use App\Auth\UserAuthenticator;
use App\Config\Config;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Monitor\MetricsService;
use App\Repository\CheckerRepository;
use App\Repository\ServiceRepository;
use App\Support\CheckerGuid;
use App\Support\ClientIp;

final class MetricsController
{
    public function __construct(
        private readonly MetricsService $metrics,
        private readonly ApiKeyGuard $guard,
        private readonly CheckerRepository $checkers,
        private readonly ServiceRepository $services,
        private readonly Config $config,
        private readonly UserAuthenticator $users,
    ) {
    }

    public function ingest(Request $request): Response
    {
        $key = $this->guard->require($request);

        $guid = CheckerGuid::fromRequest($request);
        $ip = ClientIp::fromRequest($this->config, $request);
        $checkerId = $guid === null
            ? null
            : $this->checkers->touch($guid, $key->id, $ip === '' ? null : $ip);

        $accepted = $this->metrics->ingest($key, $request->json(), $checkerId);

        return Response::json(['accepted' => $accepted], 202);
    }

    /**
     * @param array<string, string> $params
     */
    public function series(Request $request, array $params): Response
    {
        $service = (string) ($params['service'] ?? '');

        $this->authorizeRead($request, $service);

        return Response::json($this->metrics->series($service, $request->query));
    }

    /**
     * @param array<string, string> $params
     */
    public function latest(Request $request, array $params): Response
    {
        $service = (string) ($params['service'] ?? '');

        $this->authorizeRead($request, $service);

        return Response::json([
            'service' => $service,
            'metrics' => $this->metrics->latest($service, $request->query),
        ]);
    }

    private function authorizeRead(Request $request, string $service): void
    {
        $key = $this->guard->optional($request);

        if ($key !== null) {
            if (!$key->isGlobal() && !$key->canWriteFor($service)) {
                throw new ApiException(
                    'This API key is not allowed to read this service.',
                    403,
                    'forbidden',
                );
            }

            return;
        }

        $user = $this->users->user($request);

        if ($user !== null) {
            $site = $service === '' ? null : $this->services->findBySlug($service);
            $companyId = $site === null || $site['company_id'] === null ? null : (int) $site['company_id'];

            if ($site === null || $user->companyId === null || $companyId !== $user->companyId) {
                throw new ApiException('Service not found.', 404, 'not_found');
            }

            return;
        }

        if ((bool) $this->config->get('api.require_read_auth', false)) {
            throw new ApiException(
                'A valid API key or bearer token is required.',
                401,
                'unauthorized',
            );
        }

        $this->guard->enforceAnonymousRateLimit($request);
    }
}
