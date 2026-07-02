<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Application\Port\TokenGenerator;

/**
 * Default TokenGenerator: 256 bits of CSPRNG entropy hex-encoded (64 chars). High entropy is
 * why refresh tokens are stored under a fast SHA-256 (not a slow password hash) — there is no
 * low-entropy secret to protect against offline brute force, only a lookup key to compute.
 */
final class RandomRefreshTokenGenerator implements TokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
