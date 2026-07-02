<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\TransactionManager;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Result\RegistrationResult;
use App\Domain\Identity\User\Exception\EmailAlreadyRegistered;
use App\Domain\Identity\User\Exception\UsernameAlreadyTaken;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\Username;

/**
 * Register a new user: enforce email/username uniqueness, hash the password through the
 * PasswordHasher port (the domain only ever sees the hash), create the aggregate, persist
 * it (which drains UserRegistered to the outbox), and write an audit row — atomically.
 */
final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(RegisterUserCommand $c): RegistrationResult
    {
        $email = new Email($c->email);
        $username = new Username($c->username);

        if ($this->users->existsByEmail($email)) {
            throw EmailAlreadyRegistered::withEmail($email->value);
        }
        if ($this->users->existsByUsername($username)) {
            throw UsernameAlreadyTaken::withUsername($username->value);
        }

        $now = $this->clock->now();
        $hashed = HashedPassword::fromHash($this->hasher->hash($c->password));

        return $this->tx->transactional(function () use ($c, $email, $username, $hashed, $now): RegistrationResult {
            $user = User::register($this->users->nextIdentity(), $email, $username, $hashed, $now);
            $this->users->save($user);

            $this->audit->record(
                'user.registered',
                $c->actorId,
                'user:'.$user->id->value,
                [],
                ['email' => $email->value, 'username' => $username->value, 'status' => $user->status()->value],
                $c->requestId,
                null,
            );

            return new RegistrationResult($user->id->value);
        });
    }
}
