<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

/**
 * Completes an MFA login with exactly one second factor: either a TOTP code or a one-time recovery
 * code. The HTTP layer enforces that exactly one is present.
 */
final readonly class CompleteMfaLoginCommand
{
    public function __construct(
        public string $challengeToken,
        public ?string $code,
        public ?string $recoveryCode,
        public string $requestId,
    ) {}
}
