<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Port\AuditWriter;
use App\Application\Port\TransactionManager;
use App\Application\Role\Command\UpdateRoleCommand;
use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;

/**
 * Rename and/or re-describe a role (PATCH semantics: only the fields supplied change). Renaming a
 * system role is rejected by the aggregate (SystemRoleImmutable); describing one is allowed.
 */
final readonly class UpdateRole
{
    public function __construct(
        private RoleRepository $roles,
        private RoleViewFactory $views,
        private AuditWriter $audit,
        private TransactionManager $tx,
    ) {}

    public function handle(UpdateRoleCommand $c): RoleView
    {
        $role = $this->roles->getById(new RoleId($c->roleId));

        return $this->tx->transactional(function () use ($c, $role): RoleView {
            if ($c->name !== null) {
                $role->rename(new RoleName($c->name));
            }
            if ($c->descriptionProvided) {
                $role->describe($c->description);
            }
            $this->roles->save($role);

            $this->audit->record(
                'role.updated',
                $c->actorId,
                'role:'.$role->id->value,
                [],
                ['name' => $role->name()->value],
                $c->requestId,
                null,
            );

            return $this->views->make($role);
        });
    }
}
