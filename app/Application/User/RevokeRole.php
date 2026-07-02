<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Application\User\Command\RevokeRoleCommand;
use App\Application\User\Result\UserProfile;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\UserId;

/**
 * Revoke a role from a user. The role must exist (else RoleNotFound). Idempotent at the aggregate;
 * a real change bumps authz_version and emits RoleRemoved. Returns the updated profile.
 */
final readonly class RevokeRole
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(RevokeRoleCommand $c): UserProfile
    {
        $user = $this->users->getById(new UserId($c->userId));
        $role = $this->roles->getById(new RoleId($c->roleId));

        return $this->tx->transactional(function () use ($c, $user, $role): UserProfile {
            $user->revokeRole($role->id, $this->clock->now());
            $this->users->save($user);

            $this->audit->record(
                'user.role_removed',
                $c->actorId,
                'user:'.$user->id->value,
                [],
                ['role_id' => $role->id->value],
                $c->requestId,
                null,
            );

            return UserProfile::fromUser($user);
        });
    }
}
