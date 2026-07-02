<?php

declare(strict_types=1);

namespace App\Application\Role\Command;

final readonly class GrantPermissionCommand
{
    public function __construct(
        public string $roleId,
        public string $permissionName,
        public string $actorId,
        public string $requestId,
    ) {}
}
