<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\ApiKeyGuard;
use App\Config\Config;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\CheckerRepository;
use App\Repository\ServiceRepository;
use App\Support\CheckerGuid;
use App\Support\CheckerShard;
use App\Support\ClientIp;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class ServiceController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ApiKeyGuard $guard,
        private readonly CheckerRepository $checkers,
        private readonly Config $config,
    ) {
    }

    public function index(Request $request): Response
    {
        $key = $this->guard->forRead($request);
        $checkerId = $this->requireChecker($request, $key?->id);

        $services = $this->services->all();
        $shardingEnabled = (bool) $this->config->get('monitoring.sharding_enabled', true);
        $checkers = [];
        $assigned = $services;

        if ($shardingEnabled) {
            $window = max(1, (int) $this->config->get('monitoring.checker_alive_window', 300));
            $checkers = $this->checkers->alive($window, $checkerId);
            $assigned = CheckerShard::filter($services, $checkers, $checkerId);
        }

        return Response::json([
            'services' => $assigned,
            'ssl' => $this->queryBool($request->queryParam('ssl')),
            'domain' => $this->queryBool($request->queryParam('domain')),
            'shard' => [
                'enabled' => $shardingEnabled,
                'checker_id' => $checkerId,
                'assigned' => count($assigned),
                'total' => count($services),
                'checkers' => count($checkers),
            ],
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function updateMetadata(Request $request, array $params): Response
    {
        $key = $this->guard->require($request);
        $checkerId = $this->trackChecker($request, $key->id);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '' || !$key->canWriteFor($slug)) {
            throw new ApiException('This API key is not allowed to write to this site.', 403, 'forbidden');
        }

        if ($this->services->findBySlug($slug) === null) {
            throw new ApiException('Site not found.', 404, 'not_found');
        }

        $payload = $request->json();

        $hasDomain = array_key_exists('domain', $payload);
        $hasSsl = array_key_exists('ssl', $payload);

        if (!$hasDomain && !$hasSsl) {
            throw new ApiException('Provide "domain" and/or "ssl" metadata.', 422, 'validation_failed');
        }

        $domainRegisteredAt = null;
        $domainExpiresAt = null;

        if ($hasDomain) {
            $domain = $payload['domain'];

            if (
                !is_array($domain)
                || !array_key_exists('registered_at', $domain)
                || !array_key_exists('expires_at', $domain)
            ) {
                throw new ApiException(
                    'Fields "domain.registered_at" and "domain.expires_at" are required.',
                    422,
                    'validation_failed',
                );
            }

            $domainRegisteredAt = $this->parseDate($domain['registered_at'], 'domain.registered_at');
            $domainExpiresAt = $this->parseDate($domain['expires_at'], 'domain.expires_at');
        }

        $hasCertificate = null;
        $validFrom = null;
        $validTo = null;

        if ($hasSsl) {
            $ssl = $payload['ssl'];

            if (!is_array($ssl) || !array_key_exists('has_certificate', $ssl) || !is_bool($ssl['has_certificate'])) {
                throw new ApiException('Field "ssl.has_certificate" must be a boolean.', 422, 'validation_failed');
            }

            $hasCertificate = $ssl['has_certificate'];
            $validFrom = $hasCertificate ? $this->parseDate($ssl['valid_from'] ?? null, 'ssl.valid_from') : null;
            $validTo = $hasCertificate ? $this->parseDate($ssl['valid_to'] ?? null, 'ssl.valid_to') : null;
        }

        if ($hasDomain) {
            $this->services->updateDomainMetadata($slug, $domainRegisteredAt, $domainExpiresAt, $checkerId);
        }

        if ($hasSsl) {
            $this->services->updateSslMetadata($slug, (bool) $hasCertificate, $validFrom, $validTo, $checkerId);
        }

        return Response::json([
            'updated' => true,
            'site' => $this->services->findBySlug($slug),
        ])->withHeader('Cache-Control', 'no-store');
    }

    private function requireChecker(Request $request, ?int $apiKeyId): int
    {
        $guid = CheckerGuid::fromRequest($request);

        if ($guid === null) {
            throw new ApiException(
                'Header "X-Checker-Guid" is required.',
                401,
                'unauthorized',
            );
        }

        $checkerId = $this->checkers->touch($guid, $apiKeyId, $this->checkerIp($request));

        if ($checkerId === null) {
            throw new ApiException(
                'Unknown checker GUID. Send a heartbeat first.',
                401,
                'unauthorized',
            );
        }

        return $checkerId;
    }

    private function trackChecker(Request $request, ?int $apiKeyId): ?int
    {
        $guid = CheckerGuid::fromRequest($request);

        return $guid === null ? null : $this->checkers->touch($guid, $apiKeyId, $this->checkerIp($request));
    }

    private function checkerIp(Request $request): ?string
    {
        $ip = ClientIp::fromRequest($this->config, $request);

        return $ip === '' ? null : $ip;
    }

    private function parseDate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new ApiException(
                sprintf('Field "%s" must be an ISO-8601 string.', $field),
                422,
                'validation_failed',
            );
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            throw new ApiException(sprintf('Invalid "%s" date.', $field), 422, 'validation_failed');
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function queryBool(?string $value): bool
    {
        return $value !== null && in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
