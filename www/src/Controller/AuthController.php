<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\UserAuthenticator;
use App\Http\Request;
use App\Http\Response;

/**
 * Dashboard authentication API.
 */
final class AuthController
{
    public function __construct(private readonly UserAuthenticator $authenticator)
    {
    }

    public function login(Request $request): Response
    {
        $result = $this->authenticator->login($request);

        return Response::json([
            'token' => $result['token'],
            'user' => $result['user']->toArray(),
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function me(Request $request): Response
    {
        $user = $this->authenticator->require($request);

        return Response::json([
            'user' => $user->toArray(),
        ])->withHeader('Cache-Control', 'no-store');
    }

    public function register(Request $request): Response
    {
        $result = $this->authenticator->register($request);

        return Response::json([
            'token' => $result['token'],
            'user' => $result['user']->toArray(),
        ], 201)->withHeader('Cache-Control', 'no-store');
    }

    public function logout(Request $request): Response
    {
        $this->authenticator->logout($request);

        return Response::json(['ok' => true])->withHeader('Cache-Control', 'no-store');
    }
}
