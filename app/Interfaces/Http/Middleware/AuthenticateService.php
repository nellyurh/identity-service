<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the acting principal from the internal service token. Internal callers present
 * a signed service identity (verified at the mesh/gateway); here we read the resolved
 * headers the gateway injects. No request proceeds without an actor.
 *
 * Note: identity-service's own token-issuance and verification endpoints are public or
 * service-authenticated in their own right; this middleware guards the internal admin
 * surface, matching config-service's convention.
 */
final class AuthenticateService
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorId = $request->header('X-Actor-Id');
        $actorType = $request->header('X-Actor-Type', 'service');

        if (! $actorId || ! in_array($actorType, ['user', 'service'], true)) {
            throw new HttpException(401, 'AUTH_001: Missing or invalid credentials.');
        }

        $request->attributes->set('actor', ['id' => $actorId, 'type' => $actorType]);

        return $next($request);
    }
}
