<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Auth\Result\ResolvedAuthorization;
use App\Domain\Identity\User\ValueObject\UserId;

/**
 * Resolves a user's effective permissions (union across roles) and current authz_version, for
 * embedding into freshly issued access tokens. A read-model port: the adapter answers with a
 * single efficient query rather than loading each Role aggregate.
 */
interface AuthorizationResolver
{
    public function resolve(UserId $userId): ResolvedAuthorization;
}
