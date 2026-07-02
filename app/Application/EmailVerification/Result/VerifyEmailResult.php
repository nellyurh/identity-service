<?php

declare(strict_types=1);

namespace App\Application\EmailVerification\Result;

final readonly class VerifyEmailResult
{
    public function __construct(
        public string $userId,
        public bool $verified,
    ) {}
}
