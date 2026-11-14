<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\ApiKeyGuard;
use App\Http\Request;
use App\Http\Response;
use App\Monitor\MetricsService;
use App\Repository\CheckerRepository;
use App\Support\CheckerGuid;

final class MetricsController
{
    public function __construct(
        private readonly MetricsService $metrics,
        private readonly ApiKeyGuard $guard,
        private readonly CheckerRepository $checkers,
    ) {
    }

    public function ingest(Request $request): Response
    {
        $key = $this->guard->require($request);

        $guid = CheckerGuid::fromRequest($request);
        $checkerId = $guid === null ? null : $this->checkers->touch($guid, $key->id);

        $accepted = $this->metrics->ingest($key, $request->json(), $checkerId);

        return Response::json(['accepted' => $accepted], 202);
    }

    /**
     * @param array<string, string> $params
     */
    public function series(Request $request, array $params): Response
    {
        $this->guard->forRead($request);

        return Response::json($this->metrics->series((string) ($params['service'] ?? ''), $request->query));
    }

    /**
     * @param array<string, string> $params
     */
    public function latest(Request $request, array $params): Response
    {
        $this->guard->forRead($request);

        $service = (string) ($params['service'] ?? '');

        return Response::json([
            'service' => $service,
            'metrics' => $this->metrics->latest($service),
        ]);
    }
}
