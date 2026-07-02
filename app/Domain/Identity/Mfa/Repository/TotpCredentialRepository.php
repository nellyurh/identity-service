<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Repository;

use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;

interface TotpCredentialRepository
{
    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(TotpCredential $credential): void;

    public function findActiveForUser(UserId $userId): ?TotpCredential;

    public function findPendingForUser(UserId $userId): ?TotpCredential;

    public function deleteForUser(UserId $userId): void;

    public function nextIdentity(): string;
}
