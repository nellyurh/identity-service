<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

/** An MFA challenge handed back after a correct password: the opaque token (shown once) + its TTL. */
final readonly class MfaChallengeIssued
{
    public function __construct(
        public string $challengeToken,
        public int $expiresIn,
        public string $userId,
    ) {}
}
