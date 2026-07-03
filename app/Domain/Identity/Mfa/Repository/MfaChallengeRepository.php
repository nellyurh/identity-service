<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Repository;

use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

interface MfaChallengeRepository
{
    public function save(MfaChallenge $challenge): void;

    /**
     * Atomically consume the challenge: mark it used if and only if it is not already. Returns true
     * when this caller won; false when a concurrent request already consumed it. This is the
     * single-use guarantee — callers must treat false as an invalid challenge.
     */
    public function consume(MfaChallenge $challenge, DateTimeImmutable $now): bool;

    public function findByHash(string $tokenHash): ?MfaChallenge;

    /** Drop any outstanding challenges for a user so a fresh login supersedes them. */
    public function invalidateForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
