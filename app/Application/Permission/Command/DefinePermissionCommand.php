<?php

declare(strict_types=1);

namespace App\Application\Permission\Command;

final readonly class DefinePermissionCommand
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $actorId,
        public string $requestId,
    ) {}
}
