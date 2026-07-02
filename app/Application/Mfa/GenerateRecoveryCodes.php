<?php

declare(strict_types=1);

namespace App\Application\Mfa;

use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\Mfa\Repository\RecoveryCodeRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Replace a user's recovery codes with a fresh batch and return the plaintext (shown once). Only the
 * hashes are stored. Shared by MFA confirmation and the regenerate endpoint. Runs inside the caller's
 * transaction.
 */
final readonly class GenerateRecoveryCodes
{
    public function __construct(
        private RecoveryCodeRepository $codes,
        private int $count,
    ) {}

    /** @return list<string> */
    public function forUser(UserId $userId, DateTimeImmutable $now): array
    {
        $this->codes->deleteForUser($userId);

        $plaintext = [];
        $batch = [];
        for ($i = 0; $i < $this->count; $i++) {
            $code = bin2hex(random_bytes(5)); // 10 lowercase hex chars
            $plaintext[] = $code;
            $batch[] = RecoveryCode::create($this->codes->nextIdentity(), $userId, hash('sha256', $code), $now);
        }
        $this->codes->saveMany($batch);

        return $plaintext;
    }
}
