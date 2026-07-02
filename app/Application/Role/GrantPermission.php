<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Port\AuditWriter;
use App\Application\Port\TransactionManager;
use App\Application\Role\Command\GrantPermissionCommand;
use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\ValueObject\RoleId;

/**
 * Grant a catalogued permission to a role (idempotent — granting an already-held permission is a
 * no-op). The permission must exist in the catalog; unknown names are rejected. Permissions may be
 * granted to system roles (only rename/delete of system roles is forbidden).
 */
final readonly class GrantPermission
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private RoleViewFactory $views,
        private AuditWriter $audit,
        private TransactionManager $tx,
    ) {}

    public function handle(GrantPermissionCommand $c): RoleView
    {
        $role = $this->roles->getById(new RoleId($c->roleId));
        $permission = $this->permissions->getByName(new PermissionName($c->permissionName));

        return $this->tx->transactional(function () use ($c, $role, $permission): RoleView {
            $role->grantPermission($permission->id);
            $this->roles->save($role);

            $this->audit->record(
                'role.permission_granted',
                $c->actorId,
                'role:'.$role->id->value,
                [],
                ['permission' => $permission->name->value],
                $c->requestId,
                null,
            );

            return $this->views->make($role);
        });
    }
}
