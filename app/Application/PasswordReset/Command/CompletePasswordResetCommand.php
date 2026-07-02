<?php

declare(strict_types=1);

namespace App\Application\PasswordReset\Command;

final readonly class CompletePasswordResetCommand
{
    public function __construct(
        public string $token,
        public string $newPassword,
        public string $requestId,
    ) {}
}
