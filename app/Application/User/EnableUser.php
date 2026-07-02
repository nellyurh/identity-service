<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Application\User\Command\EnableUserCommand;
use App\Application\User\Result\UserProfile;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\UserId;

/** Re-enable a disabled user. Emits UserActivated. A deleted user cannot be re-enabled. */
final readonly class EnableUser
{
    public function __construct(
        private UserRepository $users,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(EnableUserCommand $c): UserProfile
    {
        $user = $this->users->getById(new UserId($c->userId));
        $before = ['status' => $user->status()->value];
        $now = $this->clock->now();

        return $this->tx->transactional(function () use ($c, $user, $before, $now): UserProfile {
            $user->enable($now);
            $this->users->save($user);

            $this->audit->record(
                'user.enabled',
                $c->actorId,
                'user:'.$user->id->value,
                $before,
                ['status' => $user->status()->value],
                $c->requestId,
                $c->reason,
            );

            return UserProfile::fromUser($user);
        });
    }
}
