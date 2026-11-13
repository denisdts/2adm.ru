<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\ApiKeyGuard;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ServiceRepository;

final class ServiceController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ApiKeyGuard $guard,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->guard->forRead($request);

        return Response::json(['services' => $this->services->all()]);
    }
}
