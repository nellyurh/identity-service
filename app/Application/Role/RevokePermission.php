<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Port\AuditWriter;
use App\Application\Port\TransactionManager;
use App\Application\Role\Command\RevokePermissionCommand;
use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\ValueObject\RoleId;

/**
 * Revoke a permission from a role (idempotent — revoking a permission the role does not hold is a
 * no-op). The permission must exist in the catalog.
 */
final readonly class RevokePermission
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private RoleViewFactory $views,
        private AuditWriter $audit,
        private TransactionManager $tx,
    ) {}

    public function handle(RevokePermissionCommand $c): RoleView
    {
        $role = $this->roles->getById(new RoleId($c->roleId));
        $permission = $this->permissions->getByName(new PermissionName($c->permissionName));

        return $this->tx->transactional(function () use ($c, $role, $permission): RoleView {
            $role->revokePermission($permission->id);
            $this->roles->save($role);

            $this->audit->record(
                'role.permission_revoked',
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
