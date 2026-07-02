<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

/**
 * A user's resolved authorization at a point in time: the deduplicated set of permission names
 * (resource.action) unioned across all assigned roles, plus the user's authz_version. Baked into
 * access tokens (permissions + authz_ver claims) so verifiers authorize offline; authz_version lets
 * them detect when a token's grants are stale after a role change.
 */
final readonly class ResolvedAuthorization
{
    /** @param list<string> $permissions */
    public function __construct(
        public array $permissions,
        public int $authzVersion,
    ) {}
}
