<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $username,
        public string $password,
        public string $actorId,
        public string $actorType,
        public string $requestId,
    ) {}
}
