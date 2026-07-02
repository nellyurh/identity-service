<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Port\AuditWriter;
use App\Application\Port\PasswordHasher;
use App\Application\User\Command\AuthenticateUserCommand;
use App\Application\User\Result\AuthenticatedUser;
use App\Domain\Identity\User\Exception\AccountNotActive;
use App\Domain\Identity\User\Exception\InvalidCredentials;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;

/**
 * Verify a user's credentials. Does NOT issue tokens — that is the authentication
 * milestone's job; this asserts the principal proved their password and is allowed to
 * authenticate. "No such user" and "wrong password" fail identically (no enumeration).
 */
final readonly class AuthenticateUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AuditWriter $audit,
    ) {}

    public function handle(AuthenticateUserCommand $c): AuthenticatedUser
    {
        $user = $this->users->findByEmail(new Email($c->email));

        if (! $user instanceof User) {
            $this->recordFailure($c, 'unknown_email');
            throw InvalidCredentials::create();
        }

        try {
            $user->assertCanAuthenticate();
        } catch (AccountNotActive $e) {
            $this->recordFailure($c, 'account_'.$user->status()->value, $user->id->value);
            throw $e;
        }

        if (! $this->hasher->verify($c->password, $user->passwordHash()->value)) {
            $this->recordFailure($c, 'bad_password', $user->id->value);
            throw InvalidCredentials::create();
        }

        $this->audit->record(
            'user.authenticated',
            $user->id->value,
            'user:'.$user->id->value,
            [],
            ['result' => 'success'],
            $c->requestId,
            null,
        );

        return new AuthenticatedUser($user->id->value, $user->status()->value, $user->isEmailVerified());
    }

    private function recordFailure(AuthenticateUserCommand $c, string $reason, string $userId = 'unknown'): void
    {
        $this->audit->record(
            'user.authentication_failed',
            $userId,
            'user:'.$userId,
            [],
            ['result' => 'failure', 'reason' => $reason],
            $c->requestId,
            null,
        );
    }
}
