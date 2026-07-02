<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Role\Role;

/**
 * Builds RoleView DTOs, resolving each role's permission ids to their names. The id→name map is
 * loaded once per call (once for a whole list), so rendering a role's grants never fans out into
 * per-permission lookups.
 */
final readonly class RoleViewFactory
{
    public function __construct(private PermissionRepository $permissions) {}

    public function make(Role $role): RoleView
    {
        return RoleView::fromRole($role, $this->nameById());
    }

    /**
     * @param  list<Role>  $roles
     * @return list<RoleView>
     */
    public function makeMany(array $roles): array
    {
        $map = $this->nameById();

        return array_map(static fn (Role $role): RoleView => RoleView::fromRole($role, $map), $roles);
    }

    /** @return array<string,string> */
    private function nameById(): array
    {
        $map = [];
        foreach ($this->permissions->all() as $permission) {
            $map[$permission->id->value] = $permission->name->value;
        }

        return $map;
    }
}
