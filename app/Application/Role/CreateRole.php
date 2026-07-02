<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Application\Role\Command\CreateRoleCommand;
use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Role\Exception\RoleNameTaken;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleName;

/**
 * Create a new (non-system) role. System roles are seeded, never created here. The name is
 * validated to snake_case by the value object and must be unique.
 */
final readonly class CreateRole
{
    public function __construct(
        private RoleRepository $roles,
        private RoleViewFactory $views,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(CreateRoleCommand $c): RoleView
    {
        $name = new RoleName($c->name);

        if ($this->roles->existsByName($name)) {
            throw RoleNameTaken::withName($name->value);
        }

        return $this->tx->transactional(function () use ($c, $name): RoleView {
            $role = Role::create($this->roles->nextIdentity(), $name, $c->description, false, $this->clock->now());
            $this->roles->save($role);

            $this->audit->record(
                'role.created',
                $c->actorId,
                'role:'.$role->id->value,
                [],
                ['name' => $name->value],
                $c->requestId,
                null,
            );

            return $this->views->make($role);
        });
    }
}
