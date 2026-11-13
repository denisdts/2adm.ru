<?php

declare(strict_types=1);

namespace App\Storage\ClickHouse;

use RuntimeException;

/**
 * ClickHouse client based on the HTTP interface.
 *
 * Query parameters are passed as URL parameters (param_<name>), so the SQL can
 * use the {name:Type} placeholders and never has to be concatenated by hand.
 */
final class ClickHouseClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function database(): string
    {
        return (string) $this->config['database'];
    }

    /**
     * Runs a SELECT and returns the rows.
     *
     * @param array<string, scalar|null> $params
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        $response = $this->request($sql . ' FORMAT JSON', '', $params);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected ClickHouse response: ' . substr($response, 0, 500));
        }

        $rows = $decoded['data'] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Runs a statement without returning rows.
     *
     * Set $withDatabase to false to run statements such as CREATE DATABASE.
     *
     * @param array<string, scalar|null> $params
     */
    public function command(string $sql, array $params = [], bool $withDatabase = true): void
    {
        $this->request($sql, '', $params, $withDatabase);
    }

    /**
     * Inserts rows using the JSONEachRow format.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function insert(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $database = $this->database();

        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new RuntimeException('Invalid ClickHouse table identifier.');
        }

        $payload = '';
        foreach ($rows as $row) {
            $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('Unable to encode a ClickHouse row.');
            }
            $payload .= $encoded . "\n";
        }

        $this->request(sprintf('INSERT INTO %s.%s FORMAT JSONEachRow', $database, $table), $payload);

        return count($rows);
    }

    public function ping(): bool
    {
        try {
            $this->request('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function request(string $sql, string $body = '', array $params = [], bool $withDatabase = true): string
    {
        $query = [];

        if ($withDatabase && $this->database() !== '') {
            $query['database'] = $this->database();
        }

        foreach ($params as $name => $value) {
            $name = (string) $name;

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                throw new RuntimeException(sprintf('Invalid ClickHouse parameter name "%s".', $name));
            }

            $query['param_' . $name] = (string) $value;
        }

        // When a data body is sent (INSERT ... FORMAT JSONEachRow) the SQL has
        // to travel in the URL, because the POST body is the payload itself.
        if ($body !== '') {
            $query['query'] = $sql;
        }

        $handle = curl_init();
        curl_setopt_array($handle, [
            CURLOPT_URL => $this->url($query),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body !== '' ? $body : $sql,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 15),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain; charset=utf-8'],
            CURLOPT_USERPWD => sprintf('%s:%s', (string) $this->config['username'], (string) $this->config['password']),
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException(sprintf('ClickHouse request failed: %s', $error !== '' ? $error : 'unknown error'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'ClickHouse returned HTTP %d: %s',
                $status,
                trim(substr((string) $response, 0, 500)),
            ));
        }

        return (string) $response;
    }

    /**
     * @param array<string, string> $query
     */
    private function url(array $query): string
    {
        return sprintf(
            'http://%s:%d/?%s',
            (string) $this->config['host'],
            (int) $this->config['port'],
            http_build_query($query),
        );
    }
}
