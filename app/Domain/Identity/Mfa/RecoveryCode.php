<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

/**
 * A single one-time MFA recovery code. Only the hash is stored; the plaintext is shown once at
 * generation. Usable in place of a TOTP code at login, then consumed (single-use).
 */
final class RecoveryCode
{
    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        public readonly string $codeHash,
        private ?DateTimeImmutable $usedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $id, UserId $userId, string $codeHash, DateTimeImmutable $now): self
    {
        return new self($id, $userId, $codeHash, null, $now);
    }

    public static function reconstitute(string $id, UserId $userId, string $codeHash, ?DateTimeImmutable $usedAt, DateTimeImmutable $createdAt): self
    {
        return new self($id, $userId, $codeHash, $usedAt, $createdAt);
    }

    public function isUsable(): bool
    {
        return ! $this->usedAt instanceof DateTimeImmutable;
    }

    public function consume(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }
}
