<?php

declare(strict_types=1);

namespace App\Domain\Identity\PasswordReset\Repository;

use App\Domain\Identity\PasswordReset\PasswordReset;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

interface PasswordResetRepository
{
    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(PasswordReset $reset): void;

    public function findByDeliveryRef(string $deliveryRef): ?PasswordReset;

    public function findByTokenHash(string $tokenHash): ?PasswordReset;

    /**
     * Atomically materialise the delivery: bind the token hash if and only if the reset is not yet
     * materialised, unused, and unexpired. Exactly one caller can win per delivery_ref — the loser's
     * token is never minted into the row, so no responder ever holds a silently-dead token.
     */
    public function materialize(PasswordReset $reset, string $tokenHash, DateTimeImmutable $now): bool;

    /**
     * Atomically consume the reset: mark it used if and only if it is materialised, unused, and
     * unexpired. True = this caller won; false = concurrent consumption or expiry — treat as invalid.
     */
    public function consume(PasswordReset $reset, DateTimeImmutable $now): bool;

    /** Drop any outstanding (unused) resets for a user so a new request supersedes them. */
    public function invalidateForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
