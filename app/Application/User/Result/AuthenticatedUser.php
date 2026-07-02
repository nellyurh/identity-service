<?php

declare(strict_types=1);

namespace App\Application\User\Result;

/**
 * The outcome of a successful credential check. Token issuance (JWT + refresh) is layered
 * on top of this by the authentication milestone; this result asserts only that the
 * principal proved their credentials and is allowed to authenticate.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public string $userId,
        public string $status,
        public bool $emailVerified,
    ) {}
}
