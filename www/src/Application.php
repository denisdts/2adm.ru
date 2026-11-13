<?php

declare(strict_types=1);

namespace App;

use App\Auth\ApiKeyAuthenticator;
use App\Auth\ApiKeyGuard;
use App\Config\Config;
use App\Controller\DashboardController;
use App\Controller\HealthController;
use App\Controller\MetricsController;
use App\Controller\ServiceController;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Monitor\MetricsService;
use App\Repository\ApiKeyRepository;
use App\Repository\MetricsRepository;
use App\Repository\ServiceRepository;
use App\Storage\ClickHouse\ClickHouseClient;
use App\Storage\MySql\MySqlConnection;
use App\Storage\Redis\RedisClient;
use Throwable;

/**
 * Minimal HTTP application: wires storage, authentication and routes.
 */
final class Application
{
    private readonly Config $config;
    private readonly Router $router;

    public static function boot(): self
    {
        return new self(Config::fromEnv());
    }

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::fromEnv();
        $this->router = new Router();

        $mysql = new MySqlConnection(self::section($this->config, 'mysql'));
        $clickhouse = new ClickHouseClient(self::section($this->config, 'clickhouse'));
        $redis = new RedisClient(self::section($this->config, 'redis'));

        $keys = new ApiKeyRepository($mysql);
        $services = new ServiceRepository($mysql);

        $authenticator = new ApiKeyAuthenticator($this->config, $keys, $redis);
        $guard = new ApiKeyGuard($authenticator, $redis, $this->config);

        $metrics = new MetricsService(new MetricsRepository($clickhouse), $redis, $this->config);

        $health = new HealthController($mysql, $clickhouse, $redis);
        $dashboard = new DashboardController();
        $metricsController = new MetricsController($metrics, $guard);
        $serviceController = new ServiceController($services, $guard);

        $router = $this->router;

        $router->get('/', static fn (Request $request): Response => $dashboard->index());
        $router->get('/healthz', static fn (Request $request): Response => $health->live());
        $router->get('/api/v1/health', static fn (Request $request): Response => $health->ready());
        $router->get('/api/v1/services', static fn (Request $request): Response => $serviceController->index($request));

        // ingestion
        $router->post('/api/v1/metrics', static fn (Request $request): Response => $metricsController->ingest($request));

        // queries
        $router->get(
            '/api/v1/metrics/{service}/latest',
            static fn (Request $request, array $params): Response => $metricsController->latest($request, $params),
        );
        $router->get(
            '/api/v1/metrics/{service}',
            static fn (Request $request, array $params): Response => $metricsController->series($request, $params),
        );
    }

    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request->method, $request->path);

            if ($match === null) {
                return $this->finalize(Response::json([
                    'error' => 'not_found',
                    'message' => sprintf('No route for %s %s.', $request->method, $request->path),
                ], 404));
            }

            [$handler, $params] = $match;
            $response = $handler($request, $params);

            if (!$response instanceof Response) {
                throw new \RuntimeException('Route handler must return ' . Response::class . '.');
            }

            return $this->finalize($response);
        } catch (ApiException $exception) {
            $body = [
                'error' => $exception->error(),
                'message' => $exception->getMessage(),
            ];

            if ($exception->context() !== []) {
                $body['details'] = $exception->context();
            }

            return $this->finalize(Response::json($body, $exception->status()));
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[%s] %s: %s in %s:%d',
                $this->config->get('app.env'),
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));

            $body = [
                'error' => 'internal_error',
                'message' => 'Internal server error.',
            ];

            if ((bool) $this->config->get('app.debug', false)) {
                $body['message'] = $exception->getMessage();
                $body['exception'] = $exception::class;
            }

            return $this->finalize(Response::json($body, 500));
        }
    }

    private function finalize(Response $response): Response
    {
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Robots-Tag', 'noindex');
    }

    /**
     * @return array<string, mixed>
     */
    private static function section(Config $config, string $name): array
    {
        $value = $config->get($name, []);

        return is_array($value) ? $value : [];
    }
}
