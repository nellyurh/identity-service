<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\TransactionManager;
use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Result\UserProfile;
use App\Domain\Identity\User\Exception\InvalidCredentials;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;

/**
 * Change a user's password. The current password must be proven first; the new password is
 * hashed through the port and the aggregate records PasswordChanged, which the repository
 * drains to the outbox. Reuse prevention (password history) is added in a later milestone.
 */
final readonly class ChangePassword
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(ChangePasswordCommand $c): UserProfile
    {
        $user = $this->users->getById(new UserId($c->userId));

        if (! $this->hasher->verify($c->currentPassword, $user->passwordHash()->value)) {
            throw InvalidCredentials::create();
        }

        $now = $this->clock->now();
        $newHash = HashedPassword::fromHash($this->hasher->hash($c->newPassword));

        return $this->tx->transactional(function () use ($c, $user, $newHash, $now): UserProfile {
            $user->changePassword($newHash, $now);
            $this->users->save($user);

            $this->audit->record(
                'user.password_changed',
                $c->actorId,
                'user:'.$user->id->value,
                [],
                [],
                $c->requestId,
                null,
            );

            return UserProfile::fromUser($user);
        });
    }
}
