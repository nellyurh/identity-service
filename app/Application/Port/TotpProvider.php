<?php

declare(strict_types=1);

namespace App\Application\Port;

use DateTimeImmutable;

/**
 * TOTP (RFC 6238) operations for MFA. The adapter owns the algorithm parameters (period, digits,
 * skew window) and the randomness source; the application layer only deals in base32 secrets and
 * codes.
 */
interface TotpProvider
{
    /** Generate a new random base32 TOTP secret. */
    public function generateSecret(): string;

    /** Build an otpauth:// provisioning URI (for QR display) binding the secret to an account. */
    public function provisioningUri(string $secret, string $accountName): string;

    /** Verify a code against the secret at $now, tolerating +/- the configured window of clock skew. */
    public function verify(string $secret, string $code, DateTimeImmutable $now): bool;
}
