<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\TransactionManager;
use App\Application\User\Command\AuthenticateUserCommand;
use App\Application\User\Result\AuthenticatedUser;
use App\Domain\Identity\User\Exception\AccountNotActive;
use App\Domain\Identity\User\Exception\InvalidCredentials;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use DateTimeImmutable;

/**
 * Verify a user's credentials. Does NOT issue tokens — this asserts the principal proved their
 * password and is allowed to authenticate. Enumeration resistance: unknown email, wrong password,
 * a temporarily locked account, and a disabled account with a wrong password all fail identically
 * (AUTH_002); the unknown-email path burns equivalent hashing time so latency doesn't leak account
 * existence. Brute force: consecutive failures increment a per-account counter that trips a
 * temporary lock (emitting UserLocked); a successful login resets it.
 */
final readonly class AuthenticateUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private int $maxAttempts,
        private int $lockDuration,
    ) {}

    public function handle(AuthenticateUserCommand $c): AuthenticatedUser
    {
        $now = $this->clock->now();
        $user = $this->users->findByEmail(new Email($c->email));

        if (! $user instanceof User) {
            // Burn comparable hashing time so "no such account" is not distinguishable by latency.
            $this->hasher->hash($c->password);
            $this->recordFailure($c, 'unknown_email');
            throw InvalidCredentials::create();
        }

        // A locked account refuses all attempts — right or wrong password — with the same generic
        // error, without verifying and without counting. Verifying while locked would let an
        // attacker keep testing guesses; a distinct "locked" response would reveal account state.
        if ($user->isLocked($now)) {
            $this->recordFailure($c, 'account_locked', $user->id->value);
            throw InvalidCredentials::create();
        }

        // Verify the password BEFORE inspecting account state (see 2I-a): a wrong password on a
        // disabled account must look exactly like any other bad credential.
        if (! $this->hasher->verify($c->password, $user->passwordHash()->value)) {
            $this->tx->transactional(function () use ($user, $now): void {
                $user->recordFailedLogin($this->maxAttempts, $this->lockDuration, $now);
                $this->users->save($user); // persists the counter and, at the threshold, the lock + UserLocked
            });

            $this->recordFailure($c, 'bad_password', $user->id->value);
            throw InvalidCredentials::create();
        }

        try {
            $user->assertCanAuthenticate();
        } catch (AccountNotActive $e) {
            $this->recordFailure($c, 'account_'.$user->status()->value, $user->id->value);
            throw $e;
        }

        if ($user->failedLoginCount() > 0 || $user->lockedUntil() instanceof DateTimeImmutable) {
            $this->tx->transactional(function () use ($user, $now): void {
                $user->recordSuccessfulLogin($now);
                $this->users->save($user);
            });
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
