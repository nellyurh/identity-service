<?php

declare(strict_types=1);

namespace App\Application\PasswordReset\Result;

final readonly class CompletePasswordResetResult
{
    public function __construct(
        public string $userId,
        public bool $reset,
    ) {}
}
