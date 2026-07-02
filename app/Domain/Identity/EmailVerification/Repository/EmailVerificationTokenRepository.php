<?php

declare(strict_types=1);

namespace App\Domain\Identity\EmailVerification\Repository;

use App\Domain\Identity\EmailVerification\EmailVerificationToken;
use App\Domain\Identity\User\ValueObject\UserId;

interface EmailVerificationTokenRepository
{
    public function save(EmailVerificationToken $token): void;

    public function findByHash(string $tokenHash): ?EmailVerificationToken;

    /** Invalidate (delete) any outstanding unused tokens for a user, so only the newest is live. */
    public function invalidateForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
