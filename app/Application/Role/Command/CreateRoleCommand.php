<?php

declare(strict_types=1);

namespace App\Application\Role\Command;

final readonly class CreateRoleCommand
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $actorId,
        public string $requestId,
    ) {}
}
