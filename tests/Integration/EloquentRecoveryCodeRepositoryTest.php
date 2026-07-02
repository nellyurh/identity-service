<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Repository\EloquentRecoveryCodeRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentRecoveryCodeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentRecoveryCodeRepository $codes;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codes = new EloquentRecoveryCodeRepository;
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    public function test_save_many_find_and_consume(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->codes->saveMany([
            RecoveryCode::create($this->codes->nextIdentity(), $userId, hash('sha256', 'one'), $this->now),
            RecoveryCode::create($this->codes->nextIdentity(), $userId, hash('sha256', 'two'), $this->now),
        ]);

        $this->assertSame(2, $this->codes->countUsableForUser($userId));

        $found = $this->codes->findByHashForUser($userId, hash('sha256', 'one'));
        $this->assertNotNull($found);
        $found?->consume($this->now);
        $this->codes->save($found);

        $this->assertSame(1, $this->codes->countUsableForUser($userId));
        $this->assertNull($this->codes->findByHashForUser($userId, hash('sha256', 'missing')));
    }

    public function test_delete_for_user(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->codes->saveMany([RecoveryCode::create($this->codes->nextIdentity(), $userId, hash('sha256', 'x'), $this->now)]);

        $this->codes->deleteForUser($userId);

        $this->assertSame(0, $this->codes->countUsableForUser($userId));
    }
}
