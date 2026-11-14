<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Config;
use App\Exception\ApiException;
use App\Http\Request;
use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use App\Storage\Redis\RedisClient;
use PDOException;

/**
 * Email/password authentication for the dashboard.
 *
 * Successful logins issue an opaque bearer token; only its SHA-256 hash is
 * stored. Failed attempts are throttled per client IP through Redis.
 */
final class UserAuthenticator
{
    private const TOKEN_PREFIX = 'sess_';
    private const FAIL_PREFIX = 'login:fail:';
    private const REGISTER_ATTEMPT_PREFIX = 'register:attempt:';
    private const TOUCH_PREFIX = 'session:used:';
    private const DUMMY_PASSWORD_HASH = '$2y$12$r4zeBmtGtkhREtciS3xM/.6V6QUpmh6aSS/JivmmElRrfdVqEjeGG';

    public function __construct(
        private readonly Config $config,
        private readonly UserRepository $users,
        private readonly UserSessionRepository $sessions,
        private readonly RedisClient $redis,
    ) {
    }

    /**
     * @return array{token: string, user: User}
     */
    public function login(Request $request): array
    {
        $this->assertNotThrottled($request, self::FAIL_PREFIX, (int) $this->config->get('auth.login_max_attempts', 10));

        $payload = $request->json();
        $login = strtolower(trim((string) ($payload['login'] ?? $payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ($login === '' || strlen($login) > 190 || $password === '') {
            throw new ApiException('A valid login and password are required.', 422, 'validation_failed');
        }

        $record = $this->users->findByLogin($login);
        $passwordHash = $record === null ? self::DUMMY_PASSWORD_HASH : (string) $record['password_hash'];
        $passwordValid = password_verify($password, $passwordHash);

        if ($record === null || !$passwordValid) {
            $this->recordAttempt($request, self::FAIL_PREFIX);

            throw new ApiException('Invalid login or password.', 401, 'invalid_credentials');
        }

        $this->clearFailures($request, self::FAIL_PREFIX);

        return $this->issueSession(User::fromRow($record), $request);
    }

    /**
     * Registers a user and signs them in immediately.
     *
     * @return array{token: string, user: User}
     */
    public function register(Request $request): array
    {
        $this->assertNotThrottled(
            $request,
            self::REGISTER_ATTEMPT_PREFIX,
            (int) $this->config->get('auth.register_max_attempts', 5),
        );

        $this->recordAttempt($request, self::REGISTER_ATTEMPT_PREFIX);

        $payload = $request->json();
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));
        $companyName = trim((string) ($payload['company'] ?? $payload['company_name'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new ApiException('A valid email is required.', 422, 'validation_failed');
        }

        if ($name === '') {
            throw new ApiException('A name is required.', 422, 'validation_failed');
        }

        if (mb_strlen($name) < 3) {
            throw new ApiException('A name must be at least 3 characters.', 422, 'validation_failed');
        }

        if ($companyName === '') {
            throw new ApiException('A company name is required.', 422, 'validation_failed');
        }

        if (mb_strlen($companyName) < 3) {
            throw new ApiException('A company name must be at least 3 characters.', 422, 'validation_failed');
        }

        if (mb_strlen($password) < 8) {
            throw new ApiException('Password must be at least 8 characters.', 422, 'validation_failed');
        }

        $name = mb_substr($name, 0, 128);
        $companyName = mb_substr($companyName, 0, 128);

        if ($this->users->findByEmail($email) !== null) {
            throw new ApiException('An account with this email already exists.', 409, 'email_taken');
        }

        try {
            $created = $this->users->createWithCompany(
                $email,
                $name,
                password_hash($password, PASSWORD_DEFAULT),
                $companyName,
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ApiException('An account with this email already exists.', 409, 'email_taken');
            }

            throw $exception;
        }

        $record = $this->users->findById($created['user_id']);

        if ($record === null) {
            throw new ApiException('Could not load the created user.', 500, 'internal_error');
        }

        return $this->issueSession(User::fromRow($record), $request);
    }

    public function require(Request $request): User
    {
        $user = $this->user($request);

        if ($user === null) {
            throw new ApiException('A valid bearer token is required.', 401, 'unauthorized');
        }

        return $user;
    }

    public function emailAvailable(string $email): bool
    {
        return $this->users->findByEmail(strtolower(trim($email))) === null;
    }

    public function user(Request $request): ?User
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return null;
        }

        $hash = $this->hash($token);
        $record = $this->sessions->findActive($hash);

        if ($record === null) {
            return null;
        }

        if ($this->redis->set(self::TOUCH_PREFIX . substr($hash, 0, 16), '1', 60)) {
            $this->sessions->touch($hash);
        }

        return User::fromRow($record);
    }

    public function logout(Request $request): void
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return;
        }

        $this->sessions->delete($this->hash($token));
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('authorization');

        if ($header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        $token = $matches[1];

        if (preg_match('/^sess_[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        return $token;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array{token: string, user: User}
     */
    private function issueSession(User $user, Request $request): array
    {
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        $ttl = max(3600, (int) $this->config->get('auth.session_ttl_days', 30) * 86400);

        $this->sessions->deleteExpiredForUser($user->id);
        $this->sessions->create(
            $user->id,
            $this->hash($token),
            $ttl,
            substr((string) $request->header('user-agent', ''), 0, 255),
            $request->clientIp($this->trustedProxies()),
        );
        $this->users->touchLastLogin($user->id);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    private function assertNotThrottled(Request $request, string $prefix, int $max): void
    {
        $key = $this->throttleKey($request, $prefix);

        if ($key === null) {
            return;
        }

        if ((int) ($this->redis->get($key) ?? 0) >= max(1, $max)) {
            throw new ApiException('Too many attempts. Try again later.', 429, 'too_many_attempts');
        }
    }

    private function recordAttempt(Request $request, string $prefix): void
    {
        $key = $this->throttleKey($request, $prefix);

        if ($key === null) {
            return;
        }

        $window = max(60, (int) $this->config->get('auth.login_window_seconds', 300));

        $this->redis->incrementBy($key, 1.0);
        $this->redis->expire($key, $window);
    }

    private function clearFailures(Request $request, string $prefix): void
    {
        $key = $this->throttleKey($request, $prefix);

        if ($key !== null) {
            $this->redis->delete($key);
        }
    }

    private function throttleKey(Request $request, string $prefix): ?string
    {
        $ip = $request->clientIp($this->trustedProxies());

        if ($ip === '') {
            return null;
        }

        return $prefix . hash('sha256', $ip);
    }

    /**
     * @return list<string>
     */
    private function trustedProxies(): array
    {
        $proxies = $this->config->get('app.trusted_proxies', []);

        return is_array($proxies) ? array_values(array_map('strval', $proxies)) : [];
    }
}
