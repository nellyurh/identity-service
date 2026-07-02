<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use App\Application\User\ChangePassword;
use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\RegisterUser;
use App\Domain\Identity\User\Event\PasswordChanged;
use App\Domain\Identity\User\Exception\InvalidCredentials;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryUserRepository;
use Tests\Support\RecordingAuditWriter;
use Tests\Support\SyncTransactionManager;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FakePasswordHasher $hasher;

    private ChangePassword $service;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository;
        $this->hasher = new FakePasswordHasher;
        $clock = new FixedClock(new DateTimeImmutable('2026-07-02T10:00:00+00:00'));

        $reg = (new RegisterUser($this->users, $this->hasher, new RecordingAuditWriter, $clock, new SyncTransactionManager))->handle(
            new RegisterUserCommand('ada@unero.com', 'ada_l', 'old-pass', 'admin-1', 'user', 'req-0'),
        );
        $this->userId = $reg->userId;
        $this->service = new ChangePassword($this->users, $this->hasher, new RecordingAuditWriter, $clock, new SyncTransactionManager);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->expectException(InvalidCredentials::class);
        $this->service->handle(new ChangePasswordCommand($this->userId, 'not-old', 'new-pass', 'admin-1', 'user', 'req-1'));
    }

    public function test_changes_hash_and_emits_event(): void
    {
        $this->service->handle(new ChangePasswordCommand($this->userId, 'old-pass', 'new-pass', 'admin-1', 'user', 'req-1'));

        $user = $this->users->findByEmail(new Email('ada@unero.com'));
        $this->assertNotNull($user);
        $this->assertTrue($this->hasher->verify('new-pass', $user->passwordHash()->value));

        $emitted = array_filter($this->users->publishedEvents, static fn (DomainEvent $e): bool => $e instanceof PasswordChanged);
        $this->assertNotEmpty($emitted);
    }
}
