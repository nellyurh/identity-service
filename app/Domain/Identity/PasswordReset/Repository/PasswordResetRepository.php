<?php

declare(strict_types=1);

namespace App\Domain\Identity\PasswordReset\Repository;

use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\User\ValueObject\UserId;

interface PasswordResetRepository
{
    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(PasswordReset $reset): void;

    public function findByDeliveryRef(string $deliveryRef): ?PasswordReset;

    public function findByTokenHash(string $tokenHash): ?PasswordReset;

    /** Drop any outstanding (unused) resets for a user so a new request supersedes them. */
    public function invalidateForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
