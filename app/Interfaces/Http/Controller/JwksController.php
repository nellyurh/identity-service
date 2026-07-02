<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controller;

use App\Application\Auth\GetJwks;
use Illuminate\Http\JsonResponse;

/** Publishes the public verification keys (JWKS) so every service verifies tokens offline. */
final class JwksController
{
    public function index(GetJwks $query): JsonResponse
    {
        return response()->json($query->handle());
    }
}
