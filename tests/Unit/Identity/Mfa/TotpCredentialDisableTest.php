<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Mfa;

use App\Domain\Identity\Mfa\Event\MFADisabled;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class TotpCredentialDisableTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function active(): TotpCredential
    {
        $credential = TotpCredential::enroll((string) new Ulid, new UserId((string) new Ulid), 'ciphertext', $this->now);
        $credential->confirm($this->now);
        $credential->releaseEvents();

        return $credential;
    }

    public function test_disable_active_emits_event(): void
    {
        $credential = $this->active();

        $credential->disable($this->now);

        $this->assertFalse($credential->isActive());
        $events = $credential->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(MFADisabled::class, $events[0]);
    }

    public function test_disable_pending_is_noop(): void
    {
        $pending = TotpCredential::enroll((string) new Ulid, new UserId((string) new Ulid), 'ciphertext', $this->now);

        $pending->disable($this->now);

        $this->assertSame([], $pending->releaseEvents());
    }

    public function test_disable_is_idempotent(): void
    {
        $credential = $this->active();
        $credential->disable($this->now);
        $credential->releaseEvents();

        $credential->disable($this->now);
        $this->assertSame([], $credential->releaseEvents());
    }
}
