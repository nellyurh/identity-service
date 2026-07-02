<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

/**
 * The freshly rotated token pair returned from a refresh: a new access token and the new
 * opaque refresh secret (the caller must persist neither — the refresh secret is returned to
 * the client exactly once and only its hash is stored).
 */
final readonly class RefreshResult
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public string $refreshToken,
        public int $refreshExpiresIn,
    ) {}
}
