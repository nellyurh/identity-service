<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiKey;

use App\Application\ApiKey\Result\GeneratedApiKeyMaterial;
use App\Application\Port\ApiKeyGenerator;

/**
 * Cryptographically-random key material: a 12-hex-char public prefix and a 64-hex-char secret
 * (32 bytes of entropy), both from random_bytes.
 */
final readonly class RandomApiKeyGenerator implements ApiKeyGenerator
{
    public function generate(): GeneratedApiKeyMaterial
    {
        return new GeneratedApiKeyMaterial(
            bin2hex(random_bytes(6)),
            bin2hex(random_bytes(32)),
        );
    }
}
