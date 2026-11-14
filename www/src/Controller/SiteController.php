<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\UserAuthenticator;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\MetricsRepository;
use App\Repository\ServiceRepository;
use App\Support\SiteHost;
use PDOException;

/**
 * Sites (monitored services) that belong to the authenticated user's company.
 */
final class SiteController
{
    private const MAX_NAME_LENGTH = 128;
    private const MAX_URL_LENGTH = 2048;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly UserAuthenticator $users,
        private readonly MetricsRepository $metrics,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->users->require($request);

        $sites = $user->companyId === null
            ? []
            : $this->services->allForCompany($user->companyId);

        return Response::json([
            'sites' => array_map(fn (array $site): array => $this->present($site), $sites),
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function create(Request $request): Response
    {
        $user = $this->users->require($request);

        if ($user->companyId === null) {
            throw new ApiException('Your account has no company.', 422, 'company_required');
        }

        $payload = $request->json();
        $raw = trim((string) ($payload['url'] ?? ''));

        if ($raw === '' || strlen($raw) > self::MAX_URL_LENGTH) {
            throw new ApiException('A site address is required.', 422, 'validation_failed');
        }

        $slug = SiteHost::fromUrl($raw);

        if ($slug === null) {
            throw new ApiException('Enter a valid site address, e.g. https://example.com.', 422, 'validation_failed');
        }

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $name = $slug;
        }

        $name = mb_substr($name, 0, self::MAX_NAME_LENGTH);

        $existing = $this->services->findBySlug($slug);

        if ($existing !== null) {
            throw $this->duplicateException($existing, $user->companyId);
        }

        try {
            $this->services->createForCompany($slug, $name, $user->companyId);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->services->findBySlug($slug);

            if ($existing !== null) {
                throw $this->duplicateException($existing, $user->companyId);
            }

            throw $exception;
        }

        $site = $this->services->findBySlug($slug);

        if ($site === null) {
            throw new ApiException('Could not create the site.', 500, 'internal_error');
        }

        return Response::json([
            'site' => $this->present($site),
        ], 201)->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $site = $this->requireSite($request, (string) ($params['slug'] ?? ''));

        return Response::json([
            'site' => $this->present($site),
        ])->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, string> $params
     */
    public function history(Request $request, array $params): Response
    {
        $site = $this->requireSite($request, (string) ($params['slug'] ?? ''));

        $limit = max(1, min(500, (int) ($request->queryParam('limit', '200') ?? '200')));
        $checkerId = $this->checkerId($request);

        return Response::json([
            'site' => $this->present($site),
            'checker_id' => $checkerId,
            'total' => $this->metrics->count((string) $site['slug'], $checkerId),
            'points' => $this->metrics->history((string) $site['slug'], $limit, $checkerId),
        ])->withHeader('Cache-Control', 'no-store');
    }

    private function checkerId(Request $request): ?int
    {
        $value = trim((string) ($request->queryParam('checker_id', '') ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new ApiException(
                'Query parameter "checker_id" must be a non-negative integer.',
                422,
                'validation_failed',
            );
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireSite(Request $request, string $slug): array
    {
        $user = $this->users->require($request);
        $site = $slug === '' ? null : $this->services->findBySlug($slug);

        $companyId = $site === null || $site['company_id'] === null ? null : (int) $site['company_id'];

        if ($site === null || $user->companyId === null || $companyId !== $user->companyId) {
            throw new ApiException('Site not found.', 404, 'not_found');
        }

        return $site;
    }

    /**
     * @param array<string, mixed> $site
     * @return array<string, mixed>
     */
    private function present(array $site): array
    {
        $hasCertificate = $site['ssl_has_certificate'] ?? null;

        return [
            'id' => (int) $site['id'],
            'slug' => (string) $site['slug'],
            'name' => (string) $site['name'],
            'company_id' => $site['company_id'] === null ? null : (int) $site['company_id'],
            'created_at' => (string) ($site['created_at'] ?? ''),
            'domain' => [
                'registered_at' => $site['domain_registered_at'] ?? null,
                'expires_at' => $site['domain_expires_at'] ?? null,
            ],
            'ssl' => [
                'has_certificate' => $hasCertificate === null ? null : (bool) (int) $hasCertificate,
                'valid_from' => $site['ssl_valid_from'] ?? null,
                'valid_to' => $site['ssl_valid_to'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $site
     */
    private function duplicateException(array $site, ?int $companyId): ApiException
    {
        $siteCompanyId = $site['company_id'] === null ? null : (int) $site['company_id'];

        if ($siteCompanyId === null || $companyId === null || $siteCompanyId !== $companyId) {
            return new ApiException(
                'This domain has already been added by another account.',
                409,
                'domain_exists',
            );
        }

        return new ApiException('This site has already been added.', 409, 'site_exists');
    }
}
