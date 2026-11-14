<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\User;
use App\Auth\UserAuthenticator;
use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\AdminRepository;
use App\Repository\UserRepository;
use App\Support\CheckerGuid;
use PDOException;

final class AdminController
{
    public function __construct(
        private readonly UserAuthenticator $users,
        private readonly AdminRepository $admin,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function users(Request $request): Response
    {
        $this->requireAdmin($request);

        return Response::json(['users' => $this->admin->users()])->withHeader('Cache-Control', 'no-store');
    }

    public function companies(Request $request): Response
    {
        $this->requireAdmin($request);

        return Response::json(['companies' => $this->admin->companies()])->withHeader('Cache-Control', 'no-store');
    }

    public function sites(Request $request): Response
    {
        $this->requireAdmin($request);

        return Response::json(['sites' => $this->admin->sites()])->withHeader('Cache-Control', 'no-store');
    }

    public function checkers(Request $request): Response
    {
        $this->requireAdmin($request);

        return Response::json(['checkers' => $this->admin->checkers()])->withHeader('Cache-Control', 'no-store');
    }

    public function notifications(Request $request): Response
    {
        $this->requireAdmin($request);

        return Response::json([
            'notifications' => $this->admin->notifications(),
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function createAdministrator(Request $request): Response
    {
        $this->requireAdmin($request);

        $payload = $request->json();
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $username = strtolower(trim((string) ($payload['username'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('A valid email is required.', 422, 'validation_failed');
        }

        if ($username === '') {
            $username = (string) strstr($email, '@', true);
        }

        $username = preg_replace('/[^a-z0-9._-]/', '', strtolower($username)) ?? '';

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $username) !== 1) {
            throw new ApiException('A valid username is required.', 422, 'validation_failed');
        }

        if ($name === '') {
            $name = $username;
        }

        $name = mb_substr($name, 0, 128);

        if (strlen($password) < 8) {
            throw new ApiException('Password must be at least 8 characters.', 422, 'validation_failed');
        }

        if ($this->userRepository->findByEmail($email) !== null) {
            throw new ApiException('A user with this email already exists.', 409, 'email_taken');
        }

        if ($this->userRepository->usernameExists($username)) {
            throw new ApiException('A user with this username already exists.', 409, 'username_taken');
        }

        try {
            $id = $this->userRepository->createAdministrator(
                $email,
                $username,
                $name,
                password_hash($password, PASSWORD_DEFAULT),
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ApiException(
                    'A user with this email or username already exists.',
                    409,
                    'user_exists',
                );
            }

            throw $exception;
        }

        $record = $this->userRepository->findById($id);

        if ($record === null) {
            throw new ApiException('Could not load the created administrator.', 500, 'internal_error');
        }

        return Response::json([
            'user' => $this->presentUser($record),
        ], 201)->withHeader('Cache-Control', 'no-store');
    }

    public function createChecker(Request $request): Response
    {
        $this->requireAdmin($request);

        $payload = $request->json();
        $name = trim((string) ($payload['name'] ?? ''));
        $bashCommand = (string) ($payload['bash_command'] ?? '');

        if ($name === '' || strlen($name) > 128) {
            throw new ApiException('Field "name" is required.', 422, 'validation_failed');
        }

        if (strlen($bashCommand) > 20000) {
            throw new ApiException('Field "bash_command" is too long.', 422, 'validation_failed');
        }

        $bashCommand = trim($bashCommand) === '' ? '' : $bashCommand;

        $rawGuid = $payload['guid'] ?? null;
        $guid = is_string($rawGuid) ? CheckerGuid::normalize($rawGuid) : null;

        if ($guid === null) {
            $guid = CheckerGuid::generate();
        }

        if ($this->admin->checkerNameExists($name)) {
            throw new ApiException('A checker with this name already exists.', 409, 'checker_exists');
        }

        if ($this->admin->checkerGuidExists($guid)) {
            throw new ApiException('A checker with this GUID already exists.', 409, 'checker_guid_exists');
        }

        try {
            $id = $this->admin->createChecker($name, $bashCommand === '' ? null : $bashCommand, $guid);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new ApiException('A checker with this name or GUID already exists.', 409, 'checker_exists');
            }

            throw $exception;
        }

        return Response::json([
            'checker' => $this->admin->checker($id),
        ], 201)->withHeader('Cache-Control', 'no-store');
    }

    private function requireAdmin(Request $request): User
    {
        $user = $this->users->require($request);

        if (!$user->isAdministrator()) {
            throw new ApiException('Administrator access is required.', 403, 'forbidden');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function presentUser(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'email' => (string) $record['email'],
            'username' => $record['username'] === null ? null : (string) $record['username'],
            'name' => (string) $record['name'],
            'group' => (string) ($record['group_code'] ?? 'clients'),
            'company_id' => $record['company_id'] === null ? null : (int) $record['company_id'],
            'company_name' => $record['company_name'] === null ? null : (string) $record['company_name'],
            'created_at' => (string) ($record['created_at'] ?? ''),
        ];
    }
}
