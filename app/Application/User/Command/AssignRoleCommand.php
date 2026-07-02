<?php

declare(strict_types=1);

namespace App\Application\User\Command;

final readonly class AssignRoleCommand
{
    public function __construct(
        public string $userId,
        public string $roleId,
        public string $actorId,
        public string $requestId,
    ) {}
}
