<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Mfa;

use App\Domain\Identity\Mfa\Event\MFAEnabled;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class TotpCredentialTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function enroll(): TotpCredential
    {
        return TotpCredential::enroll((string) new Ulid, new UserId((string) new Ulid), 'ciphertext', $this->now);
    }

    public function test_enroll_is_pending_without_events(): void
    {
        $credential = $this->enroll();

        $this->assertTrue($credential->isPending());
        $this->assertFalse($credential->isActive());
        $this->assertSame([], $credential->releaseEvents());
    }

    public function test_confirm_activates_and_emits_event(): void
    {
        $credential = $this->enroll();

        $credential->confirm($this->now);

        $this->assertTrue($credential->isActive());
        $this->assertNotNull($credential->confirmedAt());
        $events = $credential->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(MFAEnabled::class, $events[0]);
        $this->assertSame('totp', $events[0]->payload()['method']);
    }

    public function test_confirm_is_idempotent(): void
    {
        $credential = $this->enroll();
        $credential->confirm($this->now);
        $credential->releaseEvents();

        $credential->confirm($this->now);
        $this->assertSame([], $credential->releaseEvents());
    }
}
