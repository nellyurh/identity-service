<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\RegisterUser;
use App\Domain\Identity\User\Event\UserRegistered;
use App\Domain\Identity\User\Exception\EmailAlreadyRegistered;
use App\Domain\Identity\User\Exception\UsernameAlreadyTaken;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryUserRepository;
use Tests\Support\RecordingAuditWriter;
use Tests\Support\SyncTransactionManager;
use Tests\TestCase;

final class RegisterUserTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FakePasswordHasher $hasher;

    private RecordingAuditWriter $audit;

    private RegisterUser $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository;
        $this->hasher = new FakePasswordHasher;
        $this->audit = new RecordingAuditWriter;
        $this->service = new RegisterUser(
            $this->users,
            $this->hasher,
            $this->audit,
            new FixedClock(new DateTimeImmutable('2026-07-02T10:00:00+00:00')),
            new SyncTransactionManager,
        );
    }

    private function command(string $email = 'ada@unero.com', string $username = 'ada_l'): RegisterUserCommand
    {
        return new RegisterUserCommand($email, $username, 'S3cret-pass', 'admin-1', 'user', 'req-1');
    }

    public function test_registers_hashes_persists_audits_and_emits_event(): void
    {
        $result = $this->service->handle($this->command());

        $stored = $this->users->findById(new UserId($result->userId));
        $this->assertNotNull($stored);
        $this->assertTrue($stored->passwordHash()->equals(
            HashedPassword::fromHash('argon2id$S3cret-pass'),
        ));

        $this->assertInstanceOf(UserRegistered::class, $this->users->publishedEvents[0]);
        $this->assertContains('user.registered', $this->audit->actions());
    }

    public function test_rejects_duplicate_email(): void
    {
        $this->service->handle($this->command(email: 'dup@unero.com', username: 'first'));

        $this->expectException(EmailAlreadyRegistered::class);
        $this->service->handle($this->command(email: 'dup@unero.com', username: 'second'));
    }

    public function test_rejects_duplicate_username(): void
    {
        $this->service->handle($this->command(email: 'a@unero.com', username: 'shared'));

        $this->expectException(UsernameAlreadyTaken::class);
        $this->service->handle($this->command(email: 'b@unero.com', username: 'shared'));
    }
}
