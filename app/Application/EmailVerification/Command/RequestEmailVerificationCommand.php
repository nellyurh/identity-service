<?php

declare(strict_types=1);

namespace App\Application\EmailVerification\Command;

final readonly class RequestEmailVerificationCommand
{
    public function __construct(
        public string $userId,
        public string $actorId,
        public string $requestId,
    ) {}
}
