<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Repository;

use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\User\ValueObject\UserId;

interface MfaChallengeRepository
{
    public function save(MfaChallenge $challenge): void;

    public function findByHash(string $tokenHash): ?MfaChallenge;

    /** Drop any outstanding challenges for a user so a fresh login supersedes them. */
    public function invalidateForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
