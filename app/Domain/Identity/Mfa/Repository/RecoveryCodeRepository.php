<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Repository;

use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\User\ValueObject\UserId;

interface RecoveryCodeRepository
{
    /** @param list<RecoveryCode> $codes */
    public function saveMany(array $codes): void;

    public function save(RecoveryCode $code): void;

    public function findByHashForUser(UserId $userId, string $codeHash): ?RecoveryCode;

    public function countUsableForUser(UserId $userId): int;

    public function deleteForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
