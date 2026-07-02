<?php

declare(strict_types=1);

namespace App\Application\Role\Command;

final readonly class UpdateRoleCommand
{
    public function __construct(
        public string $roleId,
        public ?string $name,
        public ?string $description,
        public bool $descriptionProvided,
        public string $actorId,
        public string $requestId,
    ) {}
}
