<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

final readonly class IssuedAccessToken
{
    public function __construct(
        public string $token,
        public string $jti,
        public int $expiresIn,
        public string $expiresAt,
        public string $tokenType = 'Bearer',
    ) {}
}
