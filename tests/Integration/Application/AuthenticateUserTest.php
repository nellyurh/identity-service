<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use App\Application\User\AuthenticateUser;
use App\Application\User\Command\AuthenticateUserCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\RegisterUser;
use App\Domain\Identity\User\Exception\AccountNotActive;
use App\Domain\Identity\User\Exception\InvalidCredentials;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\Email;
use DateTimeImmutable;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryUserRepository;
use Tests\Support\RecordingAuditWriter;
use Tests\Support\SyncTransactionManager;
use Tests\TestCase;

final class AuthenticateUserTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FakePasswordHasher $hasher;

    private RecordingAuditWriter $audit;

    private AuthenticateUser $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository;
        $this->hasher = new FakePasswordHasher;
        $this->audit = new RecordingAuditWriter;

        $clock = new FixedClock(new DateTimeImmutable('2026-07-02T10:00:00+00:00'));
        (new RegisterUser($this->users, $this->hasher, new RecordingAuditWriter, $clock, new SyncTransactionManager))->handle(
            new RegisterUserCommand('ada@unero.com', 'ada_l', 'correct-horse', 'admin-1', 'user', 'req-0'),
        );

        $this->auth = $this->authAt(new DateTimeImmutable('2026-07-02T10:00:00+00:00'));
    }

    /** Build the service with the clock fixed at $at (FixedClock is immutable, so expiry tests rebuild). */
    private function authAt(DateTimeImmutable $at): AuthenticateUser
    {
        return new AuthenticateUser(
            $this->users,
            $this->hasher,
            $this->audit,
            new FixedClock($at),
            new SyncTransactionManager,
            5,   // max_attempts
            900, // lock duration (s)
        );
    }

    public function test_valid_credentials_succeed(): void
    {
        $result = $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'correct-horse', 'req-1'));

        $this->assertSame('active', $result->status);
        $this->assertFalse($result->emailVerified);
        $this->assertContains('user.authenticated', $this->audit->actions());
    }

    public function test_wrong_password_fails_without_enumeration(): void
    {
        $this->expectException(InvalidCredentials::class);
        $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'wrong', 'req-2'));
    }

    public function test_unknown_email_fails_identically(): void
    {
        $this->expectException(InvalidCredentials::class);
        $this->auth->handle(new AuthenticateUserCommand('nobody@unero.com', 'whatever', 'req-3'));
    }

    public function test_disabled_account_cannot_authenticate(): void
    {
        /** @var UserRepository $repo */
        $repo = $this->users;
        $user = $repo->findByEmail(new Email('ada@unero.com'));
        $this->assertNotNull($user);
        $user->disable(new DateTimeImmutable('2026-07-02T11:00:00+00:00'));
        $repo->save($user);

        $this->expectException(AccountNotActive::class);
        $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'correct-horse', 'req-4'));
    }

    public function test_disabled_account_with_wrong_password_does_not_reveal_status(): void
    {
        /** @var UserRepository $repo */
        $repo = $this->users;
        $user = $repo->findByEmail(new Email('ada@unero.com'));
        $this->assertNotNull($user);
        $user?->disable(new DateTimeImmutable('2026-07-02T11:00:00+00:00'));
        if ($user !== null) {
            $repo->save($user);
        }

        // Wrong password on a disabled account looks exactly like any other bad credential (AUTH_002),
        // not AccountNotActive — the account's state is never revealed before the password is proven.
        $this->expectException(InvalidCredentials::class);
        $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'wrong', 'req-5'));
    }

    private function failTimes(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            try {
                $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'wrong-'.$i, 'req-f'.$i));
                $this->fail('expected InvalidCredentials');
            } catch (InvalidCredentials) {
                // expected
            }
        }
    }

    public function test_account_locks_after_max_failed_attempts(): void
    {
        $this->failTimes(5);

        /** @var UserRepository $repo */
        $repo = $this->users;
        $user = $repo->findByEmail(new Email('ada@unero.com'));
        $this->assertTrue($user?->isLocked(new DateTimeImmutable('2026-07-02T10:00:00+00:00')) ?? false);

        // even the CORRECT password is refused while locked, with the same generic error
        try {
            $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'correct-horse', 'req-l1'));
            $this->fail('expected InvalidCredentials while locked');
        } catch (InvalidCredentials) {
            $this->assertContains('user.authentication_failed', $this->audit->actions());
        }
    }

    public function test_four_failures_do_not_lock_and_success_resets_the_counter(): void
    {
        $this->failTimes(4);

        $result = $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'correct-horse', 'req-s1'));
        $this->assertSame('active', $result->status);

        /** @var UserRepository $repo */
        $repo = $this->users;
        $this->assertSame(0, $repo->findByEmail(new Email('ada@unero.com'))?->failedLoginCount());

        // the counter reset: four MORE failures still don't lock
        $this->failTimes(4);
        $this->auth->handle(new AuthenticateUserCommand('ada@unero.com', 'correct-horse', 'req-s2'));
    }

    public function test_lock_expires_and_login_succeeds_again(): void
    {
        $this->failTimes(5);

        // 16 minutes later (lock is 15) the account is usable again
        $later = $this->authAt(new DateTimeImmutable('2026-07-02T10:16:00+00:00'));
        $result = $later->handle(new AuthenticateUserCommand('ada@unero.com', 'correct-horse', 'req-e1'));

        $this->assertSame('active', $result->status);

        /** @var UserRepository $repo */
        $repo = $this->users;
        $this->assertNull($repo->findByEmail(new Email('ada@unero.com'))?->lockedUntil());
    }
}
