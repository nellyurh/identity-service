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

        $this->auth = new AuthenticateUser($this->users, $this->hasher, $this->audit);
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
}
