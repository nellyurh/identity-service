<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Repository\EloquentMfaChallengeRepository;
use App\Infrastructure\Persistence\Repository\EloquentRecoveryCodeRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

/**
 * The single-use guarantee: consuming a challenge or recovery code is a conditional update that can
 * only be won once — the second caller (simulating a concurrent request with a stale read) gets false.
 */
final class AtomicConsumeTest extends TestCase
{
    use RefreshDatabase;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    public function test_challenge_consume_can_only_be_won_once(): void
    {
        $repo = new EloquentMfaChallengeRepository;
        $userId = new UserId((string) new Ulid);
        $challenge = MfaChallenge::create($repo->nextIdentity(), $userId, hash('sha256', 'c'), $this->now->modify('+5 minutes'), $this->now);
        $repo->save($challenge);

        // two "concurrent" requests each loaded the challenge before either consumed it
        $first = $repo->findByHash(hash('sha256', 'c'));
        $second = $repo->findByHash(hash('sha256', 'c'));
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->assertTrue($first instanceof MfaChallenge && $repo->consume($first, $this->now));
        $this->assertFalse($second instanceof MfaChallenge && $repo->consume($second, $this->now));
    }

    public function test_recovery_code_consume_can_only_be_won_once(): void
    {
        $repo = new EloquentRecoveryCodeRepository;
        $userId = new UserId((string) new Ulid);
        $repo->save(RecoveryCode::create($repo->nextIdentity(), $userId, hash('sha256', 'r'), $this->now));

        $first = $repo->findByHashForUser($userId, hash('sha256', 'r'));
        $second = $repo->findByHashForUser($userId, hash('sha256', 'r'));
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->assertTrue($first instanceof RecoveryCode && $repo->consume($first, $this->now));
        $this->assertFalse($second instanceof RecoveryCode && $repo->consume($second, $this->now));
    }
}
