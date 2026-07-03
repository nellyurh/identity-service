<?php

declare(strict_types=1);

namespace App\Domain\Identity\EmailVerification\Repository;

use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

interface EmailVerificationTokenRepository
{
    public function save(EmailVerificationToken $token): void;

    public function findByHash(string $tokenHash): ?EmailVerificationToken;

    /**
     * Atomically consume the token: mark it used if and only if it is unused AND unexpired
     * (conditional update, rows-affected checked). True = this caller won; false = a concurrent
     * request already consumed it, or it expired — treat as invalid.
     */
    public function consume(EmailVerificationToken $token, DateTimeImmutable $now): bool;

    /** Invalidate (delete) any outstanding unused tokens for a user, so only the newest is live. */
    public function invalidateForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
