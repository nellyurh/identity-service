<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $requestId,
    ) {}
}
