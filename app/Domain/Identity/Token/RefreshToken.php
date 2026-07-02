<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token;

use App\Domain\Identity\Token\Event\TokenIssued;
use App\Domain\Identity\Token\Event\TokenRevoked;
use App\Domain\Identity\Token\Exception\RefreshTokenInvalid;
use App\Domain\Identity\Token\Exception\TokenReuseDetected;
use App\Domain\Identity\Token\ValueObject\FamilyId;
use App\Domain\Identity\Token\ValueObject\RefreshTokenId;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * A single rotating refresh token. The client never receives this record — it receives an
 * opaque secret whose SHA-256 hash is stored here. Each successful refresh ROTATES the token:
 * the current one is marked rotated and a successor is minted in the SAME family. Presenting a
 * token that has already been rotated is reuse (a replayed/stolen token) and revokes the family.
 *
 * Invariants: the secret is only ever held as a hash; a token can be used at most once (rotate
 * or revoke closes it); the id and family are immutable. The repository persists the state and
 * drains recorded events into the outbox.
 */
final class RefreshToken
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly RefreshTokenId $id,
        private readonly UserId $userId,
        private readonly FamilyId $familyId,
        private readonly string $tokenHash,
        private readonly string $accessJti,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $rotatedAt,
        private ?RefreshTokenId $replacedBy,
        private ?DateTimeImmutable $revokedAt,
    ) {}

    /**
     * Mint a fresh token. Used on login (with a brand-new family) and by rotate() to create the
     * successor (same family). Records TokenIssued so consumers can track session lineage.
     */
    public static function issue(
        RefreshTokenId $id,
        UserId $userId,
        FamilyId $familyId,
        string $tokenHash,
        string $accessJti,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): self {
        $token = new self($id, $userId, $familyId, $tokenHash, $accessJti, $expiresAt, $now, null, null, null);

        $token->recordedEvents[] = new TokenIssued(
            userId: $userId->value,
            familyId: $familyId->value,
            accessJti: $accessJti,
            occurredAt: $now->format(DATE_RFC3339),
        );

        return $token;
    }

    public static function reconstitute(
        RefreshTokenId $id,
        UserId $userId,
        FamilyId $familyId,
        string $tokenHash,
        string $accessJti,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $rotatedAt,
        ?RefreshTokenId $replacedBy,
        ?DateTimeImmutable $revokedAt,
    ): self {
        return new self(
            $id, $userId, $familyId, $tokenHash, $accessJti, $expiresAt, $createdAt,
            $rotatedAt, $replacedBy, $revokedAt,
        );
    }

    /**
     * Reject the token if it cannot be exchanged. A token that was already rotated being
     * presented again is reuse — the caller must revoke the whole family. Otherwise a revoked
     * or expired token is simply invalid (no distinction leaked to the client).
     *
     * @throws TokenReuseDetected
     * @throws RefreshTokenInvalid
     */
    public function assertUsable(DateTimeImmutable $now): void
    {
        if ($this->rotatedAt instanceof DateTimeImmutable) {
            throw TokenReuseDetected::inFamily($this->familyId->value);
        }

        if ($this->revokedAt instanceof DateTimeImmutable) {
            throw RefreshTokenInvalid::because('revoked');
        }

        if ($now >= $this->expiresAt) {
            throw RefreshTokenInvalid::because('expired');
        }
    }

    /**
     * Rotate: close this token and mint its successor in the same family. The successor records
     * TokenIssued; this token records nothing new but its rotated state is persisted. Caller
     * saves both. Guards against rotating an unusable token.
     */
    public function rotate(
        RefreshTokenId $successorId,
        string $successorHash,
        string $successorAccessJti,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): self {
        $this->assertUsable($now);

        $this->rotatedAt = $now;
        $this->replacedBy = $successorId;

        return self::issue($successorId, $this->userId, $this->familyId, $successorHash, $successorAccessJti, $expiresAt, $now);
    }

    /**
     * Revoke this token (idempotent). Records a family-level TokenRevoked the first time it is
     * revoked; a second call is a no-op so bulk family revocation never double-emits.
     */
    public function revoke(RevocationReason $reason, DateTimeImmutable $now): void
    {
        if ($this->revokedAt instanceof DateTimeImmutable) {
            return;
        }

        $this->revokedAt = $now;

        $this->recordedEvents[] = new TokenRevoked(
            userId: $this->userId->value,
            familyId: $this->familyId->value,
            reason: $reason->value,
            occurredAt: $now->format(DATE_RFC3339),
        );
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function familyId(): FamilyId
    {
        return $this->familyId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function accessJti(): string
    {
        return $this->accessJti;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rotatedAt(): ?DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    public function replacedBy(): ?RefreshTokenId
    {
        return $this->replacedBy;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
