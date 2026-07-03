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
        // Verifiers cache the public keys by this header (ADR-ID-001); 5 minutes keeps rotation
        // (sign-with-current, verify-against-all-non-retired) converging quickly.
        return response()->json($query->handle())
            ->header('Cache-Control', 'public, max-age=300');
    }
}
