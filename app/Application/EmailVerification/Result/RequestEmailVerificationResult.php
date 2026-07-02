<?php

declare(strict_types=1);

namespace App\Application\EmailVerification\Result;

/**
 * Returned once to the trusted caller (the notification orchestrator) so it can email the link. The
 * raw token is never persisted or placed in an event — only its hash is stored.
 */
final readonly class RequestEmailVerificationResult
{
    public function __construct(
        public string $token,
        public string $expiresAt,
    ) {}
}
