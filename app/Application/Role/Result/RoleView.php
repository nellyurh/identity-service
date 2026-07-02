<?php

declare(strict_types=1);

namespace App\Application\Role\Result;

use App\Domain\Identity\Role\Role;

final readonly class RoleView
{
    /** @param list<string> $permissions permission names (resource.action) */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public bool $isSystem,
        public array $permissions,
    ) {}

    /**
     * @param  array<string,string>  $nameById  map of permission id => name, used to render the
     *                                          role's grant set as names rather than opaque ids
     */
    public static function fromRole(Role $role, array $nameById): self
    {
        $names = [];
        foreach ($role->permissions() as $permissionId) {
            $names[] = $nameById[$permissionId->value] ?? $permissionId->value;
        }
        sort($names);

        return new self(
            id: $role->id->value,
            name: $role->name()->value,
            description: $role->description(),
            isSystem: $role->isSystem(),
            permissions: $names,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->isSystem,
            'permissions' => $this->permissions,
        ];
    }
}
