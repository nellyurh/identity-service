<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\IntrospectCommand;
use App\Application\Auth\Result\IntrospectionResult;
use App\Application\Port\Clock;
use App\Application\Port\TokenBlacklist;
use App\Application\Port\TokenVerifier;
use App\Domain\Identity\Token\Exception\TokenInvalid;

/**
 * Introspect an access token for callers that need near-real-time revocation (high-value
 * operations): verify signature/expiry/audience statelessly, then consult the jti denylist. Any
 * failure — bad token OR blacklisted jti — is reported as simply inactive; no reason is disclosed
 * to the caller. This is the one read path that pays for the revocation check; routine verification
 * stays stateless and trusts the short access TTL.
 */
final readonly class IntrospectToken
{
    public function __construct(
        private TokenVerifier $verifier,
        private TokenBlacklist $blacklist,
        private Clock $clock,
    ) {}

    public function handle(IntrospectCommand $c): IntrospectionResult
    {
        try {
            $verified = $this->verifier->verify($c->token, $this->clock->now());
        } catch (TokenInvalid) {
            return IntrospectionResult::inactive();
        }

        if ($this->blacklist->isBlacklisted($verified->jti)) {
            return IntrospectionResult::inactive();
        }

        $tokenUse = is_string($verified->claims['token_use'] ?? null) ? $verified->claims['token_use'] : null;

        $permissions = [];
        if (is_array($verified->claims['permissions'] ?? null)) {
            foreach ($verified->claims['permissions'] as $permission) {
                if (is_string($permission)) {
                    $permissions[] = $permission;
                }
            }
        }

        $authzVersion = is_int($verified->claims['authz_ver'] ?? null) ? $verified->claims['authz_ver'] : null;

        return IntrospectionResult::active($verified->subject, $verified->jti, $tokenUse, $verified->expiresAt, $permissions, $authzVersion);
    }
}
