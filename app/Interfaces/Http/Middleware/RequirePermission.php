<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Middleware;

use App\Application\Port\Clock;
use App\Application\Port\TokenVerifier;
use App\Domain\Identity\Token\Exception\TokenInvalid;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Offline authorization: verify the Bearer access token (RS256, by kid) and assert the required
 * permission is present in its `permissions` claim — no callback to a central authorizer. This is
 * the primitive downstream services use to guard endpoints; the permissions were resolved from the
 * user's roles and baked into the token at issuance (ADR-ID-004).
 *
 * A missing/invalid token is a 401 (AUTH_010, via TokenInvalid); a valid token that lacks the
 * permission is a 403 (AUTHZ_001). Revocation is not checked here — routine authorization trusts
 * the short access TTL; high-value operations additionally introspect.
 */
final readonly class RequirePermission
{
    public function __construct(
        private TokenVerifier $verifier,
        private Clock $clock,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            throw TokenInvalid::because('missing');
        }

        $verified = $this->verifier->verify(substr($header, 7), $this->clock->now());

        $granted = [];
        if (is_array($verified->claims['permissions'] ?? null)) {
            foreach ($verified->claims['permissions'] as $entry) {
                if (is_string($entry)) {
                    $granted[] = $entry;
                }
            }
        }

        if (! in_array($permission, $granted, true)) {
            throw new HttpException(403, 'AUTHZ_001: Missing required permission.');
        }

        $request->attributes->set('actor', ['id' => $verified->subject, 'type' => 'user']);

        return $next($request);
    }
}
