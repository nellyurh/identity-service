<?php

declare(strict_types=1);

namespace App\Application\Mfa\Result;

/**
 * Returned once at enrollment: the base32 secret (to key into an authenticator manually) and the
 * otpauth:// provisioning URI (to render as a QR code). Shown once; only the encrypted secret is kept.
 */
final readonly class EnrollTotpResult
{
    public function __construct(
        public string $secret,
        public string $provisioningUri,
    ) {}
}
