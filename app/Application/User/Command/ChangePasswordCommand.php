<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
        public string $actorId,
        public string $actorType,
        public string $requestId,
    ) {}
}
