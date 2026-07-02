<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\EmailVerification;

use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class EmailVerificationTokenTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function token(?DateTimeImmutable $expiresAt = null): EmailVerificationToken
    {
        return EmailVerificationToken::create(
            (string) new Ulid,
            new UserId((string) new Ulid),
            hash('sha256', 'raw'),
            $expiresAt ?? $this->now->modify('+1 hour'),
            $this->now,
        );
    }

    public function test_usable_when_fresh(): void
    {
        $this->assertTrue($this->token()->isUsable($this->now));
    }

    public function test_not_usable_once_used(): void
    {
        $token = $this->token();
        $token->markUsed($this->now);

        $this->assertNotNull($token->usedAt());
        $this->assertFalse($token->isUsable($this->now));
    }

    public function test_not_usable_when_expired(): void
    {
        $token = $this->token($this->now->modify('-1 second'));

        $this->assertFalse($token->isUsable($this->now));
    }
}
