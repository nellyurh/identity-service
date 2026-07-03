<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Repository;

use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

interface RecoveryCodeRepository
{
    /** @param list<RecoveryCode> $codes */
    public function saveMany(array $codes): void;

    public function save(RecoveryCode $code): void;

    public function findByHashForUser(UserId $userId, string $codeHash): ?RecoveryCode;

    /**
     * Atomically consume the code: mark it used if and only if it is not already. Returns true when
     * this caller won; false when a concurrent request already spent it — treat false as invalid.
     */
    public function consume(RecoveryCode $code, DateTimeImmutable $now): bool;

    public function countUsableForUser(UserId $userId): int;

    public function deleteForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
