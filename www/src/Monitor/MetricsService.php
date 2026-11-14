<?php

declare(strict_types=1);

namespace App\Monitor;

use App\Auth\ApiKey;
use App\Config\Config;
use App\Exception\ApiException;
use App\Exception\ValidationException;
use App\Repository\MetricsRepository;
use App\Storage\Redis\RedisClient;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Metric ingestion and querying.
 */
final class MetricsService
{
    private const MAX_METRICS_PER_REQUEST = 1000;
    private const MAX_LABELS = 32;
    private const LATEST_TTL = 604800;

    private const KINDS = ['gauge', 'counter', 'histogram', 'summary', 'event'];

    private readonly DateTimeZone $utc;

    public function __construct(
        private readonly MetricsRepository $metrics,
        private readonly RedisClient $redis,
        private readonly Config $config,
    ) {
        $this->utc = new DateTimeZone('UTC');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function ingest(ApiKey $key, array $payload, ?int $checkerId = null): int
    {
        $service = trim($this->queryScalar($payload['service'] ?? null) ?? '');

        if ($service === '' && $key->serviceSlug !== null) {
            $service = $key->serviceSlug;
        }

        $service = $this->normalizeSlug($service);

        if (!$key->canWriteFor($service)) {
            throw new ApiException(
                'This API key is not allowed to write metrics for "' . $service . '".',
                403,
                'forbidden',
            );
        }

        $metrics = $payload['metrics'] ?? null;

        if ($metrics === null && isset($payload['name'])) {
            $metrics = [$payload];
        }

        if (!is_array($metrics) || $metrics === []) {
            throw new ValidationException('Field "metrics" must be a non-empty array.');
        }

        if (count($metrics) > self::MAX_METRICS_PER_REQUEST) {
            throw new ValidationException(sprintf(
                'At most %d metrics can be sent per request.',
                self::MAX_METRICS_PER_REQUEST,
            ));
        }

        $rows = [];

        foreach ($metrics as $index => $metric) {
            if (!is_array($metric)) {
                throw new ValidationException(sprintf('metrics[%d] must be an object.', $index));
            }

            $name = trim($this->queryScalar($metric['name'] ?? null) ?? '');

            if ($name === '' || strlen($name) > 200 || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]*$/', $name) !== 1) {
                throw new ValidationException(sprintf(
                    'metrics[%d].name must match [a-zA-Z0-9][a-zA-Z0-9_.:-]{0,199}.',
                    $index,
                ));
            }

            $value = $metric['value'] ?? null;

            if (!is_numeric($value)) {
                throw new ValidationException(sprintf('metrics[%d].value must be numeric.', $index));
            }

            $value = (float) $value;

            if (!is_finite($value)) {
                throw new ValidationException(sprintf('metrics[%d].value must be finite.', $index));
            }

            $kind = strtolower(trim(
                $this->queryScalar($metric['type'] ?? null)
                ?? $this->queryScalar($metric['kind'] ?? null)
                ?? 'gauge'
            ));

            if (!in_array($kind, self::KINDS, true)) {
                throw new ValidationException(sprintf(
                    'metrics[%d].type must be one of: %s.',
                    $index,
                    implode(', ', self::KINDS),
                ));
            }

            $labels = $this->normalizeLabels($metric['labels'] ?? [], $index);

            $rows[] = [
                'service' => $service,
                'name' => $name,
                'kind' => $kind,
                'value' => $value,
                'labels' => $labels === [] ? new \stdClass() : $labels,
                'checker_id' => $checkerId ?? 0,
                'ts' => $this->normalizeTimestamp($metric['ts'] ?? $metric['timestamp'] ?? null),
            ];
        }

        $this->metrics->insert($rows);

        $latestKey = 'latest:' . $service;

        foreach ($rows as $row) {
            $this->redis->hashSet($latestKey, $row['name'], (string) json_encode([
                'name' => $row['name'],
                'value' => $row['value'],
                'kind' => $row['kind'],
                'labels' => $row['labels'],
                'checker_id' => $row['checker_id'],
                'ts' => $row['ts'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->redis->expire($latestKey, self::LATEST_TTL);

        return count($rows);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{service: string, from: int, to: int, step: int, points: list<array{ts: string, value: float}>}
     */
    public function series(string $service, array $query): array
    {
        $service = $this->normalizeSlug($service);

        $window = max(60, (int) $this->config->get('monitoring.default_window', 3600));
        $now = time();

        $to = $this->queryTimestamp($this->queryScalar($query['to'] ?? null), $now);
        $from = $this->queryTimestamp($this->queryScalar($query['from'] ?? null), $to - $window);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $limit = (int) ($this->queryScalar($query['limit'] ?? null) ?? '1000');
        $limit = max(1, min(10000, $limit));

        $step = (int) ($this->queryScalar($query['step'] ?? null) ?? '0');
        $step = max(0, min(86400, $step));

        $name = trim((string) ($this->queryScalar($query['name'] ?? null) ?? ''));

        if ($name !== '' && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]*$/', $name) !== 1) {
            throw new ValidationException('Query parameter "name" is invalid.');
        }

        return [
            'service' => $service,
            'from' => $from,
            'to' => $to,
            'step' => $step,
            'points' => $this->metrics->series($service, $name === '' ? null : $name, $from, $to, $step, $limit),
        ];
    }

    /**
     * @return list<array{name: string, value: float, kind: string, labels: array<string, string>, checker_id: int, ts: string}>
     */
    public function latest(string $service): array
    {
        $service = $this->normalizeSlug($service);
        $hash = $this->redis->hash('latest:' . $service);

        ksort($hash);

        $items = [];

        foreach ($hash as $encoded) {
            $decoded = json_decode($encoded, true);

            if (!is_array($decoded)) {
                continue;
            }

            $items[] = [
                'name' => (string) ($decoded['name'] ?? ''),
                'value' => (float) ($decoded['value'] ?? 0.0),
                'kind' => (string) ($decoded['kind'] ?? 'gauge'),
                'labels' => is_array($decoded['labels'] ?? null) ? $decoded['labels'] : [],
                'checker_id' => (int) ($decoded['checker_id'] ?? 0),
                'ts' => (string) ($decoded['ts'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeLabels(mixed $labels, int $index): array
    {
        if ($labels === null || $labels === []) {
            return [];
        }

        if (!is_array($labels)) {
            throw new ValidationException(sprintf('metrics[%d].labels must be an object.', $index));
        }

        if (count($labels) > self::MAX_LABELS) {
            throw new ValidationException(sprintf(
                'metrics[%d].labels must contain at most %d entries.',
                $index,
                self::MAX_LABELS,
            ));
        }

        $normalized = [];

        foreach ($labels as $key => $value) {
            $key = (string) $key;

            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,63}$/', $key) !== 1) {
                throw new ValidationException(sprintf(
                    'metrics[%d].labels key "%s" is invalid.',
                    $index,
                    $key,
                ));
            }

            if (!is_scalar($value)) {
                throw new ValidationException(sprintf(
                    'metrics[%d].labels["%s"] must be a scalar value.',
                    $index,
                    $key,
                ));
            }

            $string = (string) $value;

            if (strlen($string) > 512) {
                throw new ValidationException(sprintf(
                    'metrics[%d].labels["%s"] is too long (max 512).',
                    $index,
                    $key,
                ));
            }

            $normalized[$key] = $string;
        }

        return $normalized;
    }

    private function normalizeTimestamp(mixed $value): string
    {
        if ($value === null || $value === '') {
            return (new DateTimeImmutable('now', $this->utc))->format('Y-m-d H:i:s.v');
        }

        if (is_numeric($value)) {
            $seconds = (float) $value;

            if ($seconds > 1e12) {
                $seconds /= 1000.0;
            }

            $whole = (int) floor($seconds);
            $millis = (int) round(($seconds - $whole) * 1000);

            if ($millis >= 1000) {
                $whole++;
                $millis -= 1000;
            }

            $date = (new DateTimeImmutable('@' . $whole))->setTimezone($this->utc);

            return $date->format('Y-m-d H:i:s') . sprintf('.%03d', max(0, $millis));
        }

        if (!is_string($value)) {
            throw new ValidationException('Metric timestamp must be a unix timestamp or an ISO-8601 string.');
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            throw new ValidationException(sprintf('Unable to parse timestamp "%s".', $value));
        }

        return $date->setTimezone($this->utc)->format('Y-m-d H:i:s.v');
    }

    private function queryScalar(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if ($value === null || $value === false) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function queryTimestamp(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            $seconds = (float) $value;

            if ($seconds > 1e12) {
                $seconds /= 1000.0;
            }

            return (int) floor($seconds);
        }

        if (!is_string($value)) {
            throw new ValidationException('Timestamps must be unix timestamps or ISO-8601 strings.');
        }

        try {
            return (new DateTimeImmutable($value))->getTimestamp();
        } catch (Exception) {
            throw new ValidationException(sprintf('Unable to parse timestamp "%s".', $value));
        }
    }

    private function normalizeSlug(string $service): string
    {
        $service = trim($service);

        if ($service === '' || strlen($service) > 64 || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $service) !== 1) {
            throw new ValidationException('Service name must match [a-zA-Z0-9][a-zA-Z0-9_.-]{0,63}.');
        }

        return $service;
    }
}
