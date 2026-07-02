<?php

declare(strict_types=1);

namespace App\Application\Permission;

use App\Application\Permission\Command\DefinePermissionCommand;
use App\Application\Permission\Result\PermissionView;
use App\Application\Port\AuditWriter;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Permission\Exception\PermissionAlreadyExists;
use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionName;

/**
 * Define a new (non-system) permission in the catalog. System permissions are seeded, never
 * created through this path — anything defined here is tenant/admin-authored and marked
 * non-system. The name is validated to `resource.action` by the value object.
 */
final readonly class DefinePermission
{
    public function __construct(
        private PermissionRepository $permissions,
        private AuditWriter $audit,
        private TransactionManager $tx,
    ) {}

    public function handle(DefinePermissionCommand $c): PermissionView
    {
        $name = new PermissionName($c->name);

        if ($this->permissions->existsByName($name)) {
            throw PermissionAlreadyExists::withName($name->value);
        }

        return $this->tx->transactional(function () use ($c, $name): PermissionView {
            $permission = Permission::define(
                $this->permissions->nextIdentity(),
                $name,
                $c->description,
                false,
            );
            $this->permissions->save($permission);

            $this->audit->record(
                'permission.defined',
                $c->actorId,
                'permission:'.$permission->id->value,
                [],
                ['name' => $name->value],
                $c->requestId,
                null,
            );

            return PermissionView::fromPermission($permission);
        });
    }
}
