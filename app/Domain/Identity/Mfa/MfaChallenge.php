<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa;

use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

/**
 * A short-lived, single-use, opaque challenge issued after a correct password when the user has MFA.
 * It proves "password already verified" and is exchanged (with a valid TOTP code) for a session. Only
 * the hash is stored; the raw token is returned to the client once. Being opaque (not a JWT), it can
 * never be mistaken for an access token.
 */
final class MfaChallenge
{
    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        public readonly string $tokenHash,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $usedAt,
        private readonly DateTimeImmutable $createdAt,
        private int $failedAttempts = 0,
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
        int $failedAttempts = 0,
    ): self {
        return new self($id, $userId, $tokenHash, $expiresAt, $usedAt, $createdAt, $failedAttempts);
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return ! $this->usedAt instanceof DateTimeImmutable && $this->expiresAt > $now;
    }

    public function consume(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    /**
     * Count a wrong second-factor submission. At $maxAttempts the challenge is invalidated (marked
     * used), forcing the caller back through the password step — which re-engages the login rate
     * limits and account lockout. Guards the 6-digit TOTP space against per-challenge brute force.
     */
    public function recordFailedAttempt(int $maxAttempts, DateTimeImmutable $now): void
    {
        $this->failedAttempts++;
        if ($this->failedAttempts >= $maxAttempts) {
            $this->usedAt = $now;
        }
    }

    public function failedAttempts(): int
    {
        return $this->failedAttempts;
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
