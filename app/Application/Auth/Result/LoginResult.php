<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

final readonly class LoginResult
{
    public function __construct(
        public string $userId,
        public string $status,
        public bool $emailVerified,
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
    ) {}
}
