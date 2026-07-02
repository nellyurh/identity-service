<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

final readonly class VerifiedToken
{
    /** @param array<string,mixed> $claims */
    public function __construct(
        public string $subject,
        public string $jti,
        public array $claims,
        public string $expiresAt,
    ) {}
}
