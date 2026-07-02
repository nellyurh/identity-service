<?php

declare(strict_types=1);

namespace App\Application\ApiKey\Result;

/**
 * Freshly generated key material: the public prefix and the plaintext secret. The application
 * service assembles the full `unero_<env>_<prefix>.<secret>` string and hashes the secret; neither
 * the secret nor the full key is persisted.
 */
final readonly class GeneratedApiKeyMaterial
{
    public function __construct(
        public string $prefix,
        public string $secret,
    ) {}
}
