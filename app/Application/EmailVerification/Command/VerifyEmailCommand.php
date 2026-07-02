<?php

declare(strict_types=1);

namespace App\Application\EmailVerification\Command;

final readonly class VerifyEmailCommand
{
    public function __construct(
        public string $token,
        public string $requestId,
    ) {}
}
