<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\Mfa\Event\MFADisabled;
use App\Domain\Identity\Mfa\Event\MFAEnabled;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * A user's TOTP (authenticator-app) second factor. Enrollment stores the secret — encrypted at rest —
 * in a pending state; the user proves possession with a valid code to activate it. The secret is
 * recoverable (encrypted, not hashed) because the server must recompute codes to verify them.
 */
final class TotpCredential
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        private readonly string $encryptedSecret,
        private TotpStatus $status,
        private ?DateTimeImmutable $confirmedAt,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function enroll(string $id, UserId $userId, string $encryptedSecret, DateTimeImmutable $now): self
    {
        return new self($id, $userId, $encryptedSecret, TotpStatus::Pending, null, $now, $now);
    }

    public static function reconstitute(
        string $id,
        UserId $userId,
        string $encryptedSecret,
        TotpStatus $status,
        ?DateTimeImmutable $confirmedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $userId, $encryptedSecret, $status, $confirmedAt, $createdAt, $updatedAt);
    }

    /** Activate the credential after a valid code. Idempotent once active. Emits MFAEnabled. */
    public function confirm(DateTimeImmutable $now): void
    {
        if ($this->status === TotpStatus::Active) {
            return;
        }
        $this->status = TotpStatus::Active;
        $this->confirmedAt = $now;
        $this->updatedAt = $now;

        $this->recordedEvents[] = new MFAEnabled($this->userId->value, 'totp', $now->format(DATE_RFC3339));
    }

    /** Turn off an active second factor. No-op unless active. Emits MFADisabled. */
    public function disable(DateTimeImmutable $now): void
    {
        if ($this->status !== TotpStatus::Active) {
            return;
        }
        $this->status = TotpStatus::Disabled;
        $this->updatedAt = $now;

        $this->recordedEvents[] = new MFADisabled($this->userId->value, 'totp', $now->format(DATE_RFC3339));
    }

    public function isActive(): bool
    {
        return $this->status === TotpStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->status === TotpStatus::Pending;
    }

    public function encryptedSecret(): string
    {
        return $this->encryptedSecret;
    }

    public function status(): TotpStatus
    {
        return $this->status;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
