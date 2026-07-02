<?php

declare(strict_types=1);

namespace App\Application\Port;

use DateTimeImmutable;

/**
 * Denylist for access-token jtis. Access tokens are stateless and self-expire within the access
 * TTL, so entries are stored only until the token would have expired anyway (self-cleaning).
 * Consulted by introspection, not by routine stateless verification — high-value callers pay for
 * a near-real-time revocation check; routine reads trust the short TTL.
 */
interface TokenBlacklist
{
    /** Deny a jti until $expiresAt (no-op if that instant has already passed). */
    public function blacklist(string $jti, DateTimeImmutable $expiresAt): void;

    public function isBlacklisted(string $jti): bool;
}
