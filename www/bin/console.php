#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Config\Config;
use App\Migration\ClickHouseMigrator;
use App\Migration\MySqlMigrator;
use App\Repository\ApiKeyRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Storage\ClickHouse\ClickHouseClient;
use App\Storage\MySql\MySqlConnection;
use App\Storage\Redis\RedisClient;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies are not installed (vendor/autoload.php missing).\n");
    exit(1);
}

require $autoload;

/**
 * @return array<string, mixed>
 */
function section(Config $config, string $name): array
{
    $value = $config->get($name, []);

    return is_array($value) ? $value : [];
}

function line(string $message): void
{
    fwrite(STDOUT, $message . "\n");
}

function fail(string $message): never
{
    fwrite(STDERR, 'error: ' . $message . "\n");
    exit(1);
}

$config = Config::fromEnv();

$mysql = new MySqlConnection(section($config, 'mysql'));
$clickhouse = new ClickHouseClient(section($config, 'clickhouse'));
$redis = new RedisClient(section($config, 'redis'));

$serviceRepository = new ServiceRepository($mysql);
$apiKeyRepository = new ApiKeyRepository($mysql);
$userRepository = new UserRepository($mysql);

$command = $argv[1] ?? 'help';

try {
    switch ($command) {
        case 'migrate':
            (new MySqlMigrator($mysql))->migrate();
            line('MySQL schema is up to date.');

            (new ClickHouseMigrator($clickhouse, $config))->migrate();
            line('ClickHouse schema is up to date.');
            break;

        case 'service:create':
            $slug = trim((string) ($argv[2] ?? ''));
            $name = trim((string) ($argv[3] ?? $slug));

            if ($slug === '' || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,63}$/', $slug) !== 1) {
                fail('usage: service:create <slug> [name]  (slug: [a-zA-Z0-9][a-zA-Z0-9_.-]{0,63})');
            }

            if ($name === '') {
                $name = $slug;
            }

            if ($serviceRepository->findBySlug($slug) !== null) {
                fail(sprintf('service "%s" already exists', $slug));
            }

            $id = $serviceRepository->create($slug, $name);
            line(sprintf('service "%s" created (id=%d)', $slug, $id));
            break;

        case 'api-key:create':
            $name = trim((string) ($argv[2] ?? ''));

            if ($name === '') {
                fail('usage: api-key:create <name> --service=<slug> | --global');
            }

            $scope = null;
            $serviceSlug = null;

            foreach (array_slice($argv, 3) as $argument) {
                if ($argument === '--global') {
                    $scope = 'global';
                    continue;
                }

                if (str_starts_with($argument, '--service=')) {
                    $serviceSlug = substr($argument, strlen('--service='));
                    $scope = 'service';
                    continue;
                }

                fail(sprintf('unknown argument "%s"', $argument));
            }

            if ($scope === null) {
                fail('specify --service=<slug> for a service key or --global for an admin key');
            }

            $serviceId = null;

            if ($scope === 'service') {
                $service = $serviceSlug === null ? null : $serviceRepository->findBySlug($serviceSlug);

                if ($service === null) {
                    fail(sprintf('service "%s" does not exist (create it with service:create)', (string) $serviceSlug));
                }

                $serviceId = (int) $service['id'];
            }

            $prefix = bin2hex(random_bytes(4));
            $secret = bin2hex(random_bytes(32));
            $rawKey = sprintf('mk_%s_%s', $prefix, $secret);
            $hash = hash_hmac('sha256', $secret, (string) $config->get('app.key'));

            $id = $apiKeyRepository->create($name, $prefix, $hash, $scope, $serviceId);

            line(sprintf('API key #%d created (scope=%s).', $id, $scope));
            line('');
            line($rawKey);
            line('');
            line('Store it now: the secret is not stored anywhere and cannot be shown again.');
            break;

        case 'api-key:list':
            $keys = $apiKeyRepository->all();

            if ($keys === []) {
                line('no API keys');
                break;
            }

            foreach ($keys as $key) {
                line(sprintf(
                    '#%-3d %-8s scope=%-7s service=%-16s last_used_at=%s name=%s',
                    (int) $key['id'],
                    (string) $key['prefix'],
                    (string) $key['scope'],
                    (string) ($key['service_slug'] ?? '-'),
                    (string) ($key['last_used_at'] ?? 'never'),
                    (string) $key['name'],
                ));
            }
            break;

        case 'user:create':
            $positional = [];
            $password = null;
            $company = null;

            foreach (array_slice($argv, 2) as $argument) {
                if (str_starts_with($argument, '--password=')) {
                    $password = substr($argument, strlen('--password='));
                    continue;
                }

                if (str_starts_with($argument, '--company=')) {
                    $company = substr($argument, strlen('--company='));
                    continue;
                }

                if (str_starts_with($argument, '--')) {
                    fail(sprintf('unknown argument "%s"', $argument));
                }

                $positional[] = $argument;
            }

            $email = strtolower(trim((string) ($positional[0] ?? '')));
            $name = trim((string) ($positional[1] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                fail('usage: user:create <email> [name] [--company=<name>] [--password=<password>]');
            }

            if ($name === '') {
                $name = (string) strstr($email, '@', true);
            }

            if ($company === null || trim($company) === '') {
                $company = $name;
            }

            $company = mb_substr(trim($company), 0, 128);

            if ($password === null) {
                fwrite(STDOUT, 'Password: ');
                $password = trim((string) fgets(STDIN));
            }

            if (strlen($password) < 8) {
                fail('password must be at least 8 characters');
            }

            if ($userRepository->findByEmail($email) !== null) {
                fail(sprintf('user "%s" already exists', $email));
            }

            $created = $userRepository->createWithCompany(
                $email,
                $name,
                password_hash($password, PASSWORD_DEFAULT),
                $company,
            );
            line(sprintf(
                'user "%s" created (id=%d, company_id=%d)',
                $email,
                $created['user_id'],
                $created['company_id'],
            ));
            break;

        case 'health':
            line(sprintf('mysql:      %s', $mysql->ping() ? 'ok' : 'down'));
            line(sprintf('clickhouse: %s', $clickhouse->ping() ? 'ok' : 'down'));
            line(sprintf('redis:      %s', $redis->ping() ? 'ok' : 'down'));
            break;

        case 'help':
        default:
            line('Available commands:');
            line('  migrate                              apply MySQL + ClickHouse schema');
            line('  service:create <slug> [name]         register a monitored service');
            line('  api-key:create <name> --service=<slug> | --global');
            line('  api-key:list                         list active API keys');
            line('  user:create <email> [name] [--company=<name>] [--password=<password>]');
            line('                                       create a dashboard user and company');
            line('  health                               check backend connectivity');
            break;
    }
} catch (Throwable $exception) {
    fail($exception->getMessage());
}
