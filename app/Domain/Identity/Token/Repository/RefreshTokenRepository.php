<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token\Repository;

use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\RevocationReason;
use App\Domain\Identity\Token\ValueObject\FamilyId;
use App\Domain\Identity\Token\ValueObject\RefreshTokenId;
use DateTimeImmutable;

interface RefreshTokenRepository
{
    /** Look a token up by the SHA-256 hash of its opaque secret. Null if unknown. */
    public function findByHash(string $tokenHash): ?RefreshToken;

    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(RefreshToken $token): void;

    /**
     * Revoke every still-active token in a family in one pass and emit a single family-level
     * TokenRevoked. Used on logout and on reuse detection. Idempotent: tokens already revoked
     * are skipped and no duplicate event is emitted when nothing changes.
     */
    public function revokeFamily(FamilyId $familyId, RevocationReason $reason, DateTimeImmutable $now): void;

    public function nextIdentity(): RefreshTokenId;

    public function nextFamilyIdentity(): FamilyId;
}
