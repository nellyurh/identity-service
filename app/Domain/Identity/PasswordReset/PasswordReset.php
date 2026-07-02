<?php

declare(strict_types=1);

namespace App\Domain\Identity\PasswordReset;

use App\Domain\Identity\PasswordReset\Event\PasswordResetRequested;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * A password-reset request. Created at request time with only an opaque delivery_ref (carried in the
 * PasswordResetRequested event). The actual token is minted later, at materialisation (the
 * authenticated callback), so the raw token never exists at request time and never enters an event —
 * only its hash is ever stored. The reset is then redeemed once to change the password.
 */
final class PasswordReset
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        public readonly string $deliveryRef,
        private ?string $tokenHash,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $materializedAt,
        private ?DateTimeImmutable $usedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $id, UserId $userId, string $deliveryRef, DateTimeImmutable $expiresAt, DateTimeImmutable $now): self
    {
        $reset = new self($id, $userId, $deliveryRef, null, $expiresAt, null, null, $now);
        $reset->recordedEvents[] = new PasswordResetRequested($userId->value, $deliveryRef, $now->format(DATE_RFC3339));

        return $reset;
    }

    public static function reconstitute(
        string $id,
        UserId $userId,
        string $deliveryRef,
        ?string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $materializedAt,
        ?DateTimeImmutable $usedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $deliveryRef, $tokenHash, $expiresAt, $materializedAt, $usedAt, $createdAt);
    }

    /** The delivery_ref can still be exchanged for a token (not yet materialised, not used, not expired). */
    public function isDeliverable(DateTimeImmutable $now): bool
    {
        return ! $this->materializedAt instanceof DateTimeImmutable && ! $this->usedAt instanceof DateTimeImmutable && $this->expiresAt > $now;
    }

    /** Mint-time: bind the token hash and mark the ref materialised. */
    public function materialize(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->tokenHash = $tokenHash;
        $this->materializedAt = $now;
    }

    /** The token can still be redeemed (materialised, not used, not expired). */
    public function isRedeemable(DateTimeImmutable $now): bool
    {
        return $this->tokenHash !== null && $this->materializedAt instanceof DateTimeImmutable && ! $this->usedAt instanceof DateTimeImmutable && $this->expiresAt > $now;
    }

    public function consume(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    public function tokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function materializedAt(): ?DateTimeImmutable
    {
        return $this->materializedAt;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
