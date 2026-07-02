<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class DeleteUserCommand
{
    public function __construct(
        public string $userId,
        public string $actorId,
        public string $actorType,
        public string $requestId,
        public ?string $reason = null,
    ) {}
}
