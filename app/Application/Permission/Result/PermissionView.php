<?php

declare(strict_types=1);

namespace App\Application\Permission\Result;

use App\Domain\Identity\Permission\Permission;

final readonly class PermissionView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $resource,
        public string $action,
        public ?string $description,
        public bool $isSystem,
    ) {}

    public static function fromPermission(Permission $permission): self
    {
        return new self(
            id: $permission->id->value,
            name: $permission->name->value,
            resource: $permission->name->resource,
            action: $permission->name->action,
            description: $permission->description,
            isSystem: $permission->isSystem,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'resource' => $this->resource,
            'action' => $this->action,
            'description' => $this->description,
            'is_system' => $this->isSystem,
        ];
    }
}
