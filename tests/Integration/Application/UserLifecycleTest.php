<?php

declare(strict_types=1);

namespace Tests\Integration\Application;

use App\Application\User\Command\DeleteUserCommand;
use App\Application\User\Command\DisableUserCommand;
use App\Application\User\Command\EnableUserCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\DeleteUser;
use App\Application\User\DisableUser;
use App\Application\User\EnableUser;
use App\Application\User\GetUser;
use App\Application\User\RegisterUser;
use DateTimeImmutable;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryUserRepository;
use Tests\Support\RecordingAuditWriter;
use Tests\Support\SyncTransactionManager;
use Tests\TestCase;

final class UserLifecycleTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FixedClock $clock;

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository;
        $this->clock = new FixedClock(new DateTimeImmutable('2026-07-02T10:00:00+00:00'));

        $reg = (new RegisterUser($this->users, new FakePasswordHasher, new RecordingAuditWriter, $this->clock, new SyncTransactionManager))->handle(
            new RegisterUserCommand('ada@unero.com', 'ada_l', 'pass', 'admin-1', 'user', 'req-0'),
        );
        $this->userId = $reg->userId;
    }

    public function test_disable_then_enable_round_trip(): void
    {
        (new DisableUser($this->users, new RecordingAuditWriter, $this->clock, new SyncTransactionManager))
            ->handle(new DisableUserCommand($this->userId, 'admin-1', 'user', 'req-1', 'policy'));

        $this->assertSame('disabled', (new GetUser($this->users))->byId($this->userId)->status);

        (new EnableUser($this->users, new RecordingAuditWriter, $this->clock, new SyncTransactionManager))
            ->handle(new EnableUserCommand($this->userId, 'admin-1', 'user', 'req-2'));

        $this->assertSame('active', (new GetUser($this->users))->byId($this->userId)->status);
    }

    public function test_delete_soft_deletes(): void
    {
        (new DeleteUser($this->users, new RecordingAuditWriter, $this->clock, new SyncTransactionManager))
            ->handle(new DeleteUserCommand($this->userId, 'admin-1', 'user', 'req-3'));

        $this->assertSame('deleted', (new GetUser($this->users))->byId($this->userId)->status);
    }

    public function test_get_user_by_email_and_username(): void
    {
        $q = new GetUser($this->users);
        $this->assertSame($this->userId, $q->byEmail('ada@unero.com')->userId);
        $this->assertSame($this->userId, $q->byUsername('ada_l')->userId);
        $this->assertNull($q->byEmail('missing@unero.com'));
    }
}
