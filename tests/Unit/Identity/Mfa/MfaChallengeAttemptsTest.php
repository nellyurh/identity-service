<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Mfa;

use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class MfaChallengeAttemptsTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function challenge(): MfaChallenge
    {
        return MfaChallenge::create((string) new Ulid, new UserId((string) new Ulid), hash('sha256', 'c'), $this->now->modify('+5 minutes'), $this->now);
    }

    public function test_stays_usable_below_the_cap(): void
    {
        $challenge = $this->challenge();

        for ($i = 0; $i < 4; $i++) {
            $challenge->recordFailedAttempt(5, $this->now);
        }

        $this->assertSame(4, $challenge->failedAttempts());
        $this->assertTrue($challenge->isUsable($this->now));
    }

    public function test_invalidated_at_the_cap(): void
    {
        $challenge = $this->challenge();

        for ($i = 0; $i < 5; $i++) {
            $challenge->recordFailedAttempt(5, $this->now);
        }

        $this->assertFalse($challenge->isUsable($this->now));
    }
}
