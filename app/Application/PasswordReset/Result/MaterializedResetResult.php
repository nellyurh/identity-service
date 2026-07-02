<?php

declare(strict_types=1);

namespace App\Application\PasswordReset\Result;

/**
 * Returned once to the authenticated notification service so it can email the reset link. Carries the
 * recipient email and the freshly-minted raw token — over the authenticated internal call only; the
 * raw token is never persisted (only its hash) and never placed in an event.
 */
final readonly class MaterializedResetResult
{
    public function __construct(
        public string $email,
        public string $token,
        public string $expiresAt,
    ) {}
}
