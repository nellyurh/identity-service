<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class DisableUserCommand
{
    public function __construct(
        public string $userId,
        public string $actorId,
        public string $actorType,
        public string $requestId,
        public ?string $reason = null,
    ) {}
}
