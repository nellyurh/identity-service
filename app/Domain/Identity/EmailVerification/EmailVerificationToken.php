<?php

declare(strict_types=1);

namespace App\Domain\Identity\EmailVerification;

use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

/**
 * A single-use, expiring token proving a user can receive mail at their address. Only the token's
 * SHA-256 hash is persisted; the raw value is shown once (delivered by email) and looked up by hash.
 * Carries no behavior beyond its own lifecycle — verifying the email mutates the User aggregate.
 */
final class EmailVerificationToken
{
    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        public readonly string $tokenHash,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $usedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $id, UserId $userId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): self
    {
        return new self($id, $userId, $tokenHash, $expiresAt, null, $now);
    }

    public static function reconstitute(
        string $id,
        UserId $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $tokenHash, $expiresAt, $usedAt, $createdAt);
    }

    /** Usable = not yet consumed and not expired. */
    public function isUsable(DateTimeImmutable $now): bool
    {
        return ! $this->usedAt instanceof DateTimeImmutable && $this->expiresAt > $now;
    }

    public function markUsed(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
