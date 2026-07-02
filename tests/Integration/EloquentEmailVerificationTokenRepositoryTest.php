<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Repository\EloquentEmailVerificationTokenRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentEmailVerificationTokenRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentEmailVerificationTokenRepository $tokens;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = new EloquentEmailVerificationTokenRepository;
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function make(UserId $userId, string $raw): EmailVerificationToken
    {
        return EmailVerificationToken::create(
            $this->tokens->nextIdentity(),
            $userId,
            hash('sha256', $raw),
            $this->now->modify('+1 hour'),
            $this->now,
        );
    }

    public function test_save_and_find_by_hash_round_trips(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->tokens->save($this->make($userId, 'raw-1'));

        $found = $this->tokens->findByHash(hash('sha256', 'raw-1'));
        $this->assertNotNull($found);
        $this->assertTrue($found?->userId->equals($userId));
        $this->assertNull($this->tokens->findByHash(hash('sha256', 'nope')));
    }

    public function test_invalidate_for_user_removes_unused(): void
    {
        $userId = new UserId((string) new Ulid);
        $this->tokens->save($this->make($userId, 'raw-1'));
        $this->tokens->save($this->make($userId, 'raw-2'));

        $this->tokens->invalidateForUser($userId);

        $this->assertNull($this->tokens->findByHash(hash('sha256', 'raw-1')));
        $this->assertNull($this->tokens->findByHash(hash('sha256', 'raw-2')));
    }
}
