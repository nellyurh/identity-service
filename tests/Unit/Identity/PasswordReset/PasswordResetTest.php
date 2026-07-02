<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\PasswordReset;

use App\Domain\Identity\PasswordReset\Event\PasswordResetRequested;
use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class PasswordResetTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function newReset(?DateTimeImmutable $expiresAt = null): PasswordReset
    {
        return PasswordReset::create(
            (string) new Ulid,
            new UserId((string) new Ulid),
            bin2hex(random_bytes(16)),
            $expiresAt ?? $this->now->modify('+1 hour'),
            $this->now,
        );
    }

    public function test_create_records_event_without_token_or_email(): void
    {
        $reset = $this->newReset();
        $events = $reset->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PasswordResetRequested::class, $events[0]);
        $payload = $events[0]->payload();
        $this->assertArrayHasKey('delivery_ref', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function test_deliverable_until_materialised(): void
    {
        $reset = $this->newReset();
        $this->assertTrue($reset->isDeliverable($this->now));
        $this->assertFalse($reset->isRedeemable($this->now));

        $reset->materialize(hash('sha256', 'raw'), $this->now);
        $this->assertFalse($reset->isDeliverable($this->now));
        $this->assertTrue($reset->isRedeemable($this->now));
        $this->assertSame(hash('sha256', 'raw'), $reset->tokenHash());
    }

    public function test_consume_ends_redeemability(): void
    {
        $reset = $this->newReset();
        $reset->materialize(hash('sha256', 'raw'), $this->now);

        $reset->consume($this->now);
        $this->assertFalse($reset->isRedeemable($this->now));
        $this->assertNotNull($reset->usedAt());
    }

    public function test_expiry_blocks_delivery_and_redemption(): void
    {
        $reset = $this->newReset($this->now->modify('-1 second'));
        $this->assertFalse($reset->isDeliverable($this->now));

        $reset->materialize(hash('sha256', 'raw'), $this->now);
        $this->assertFalse($reset->isRedeemable($this->now));
    }
}
