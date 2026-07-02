<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey;

use App\Domain\Identity\ApiKey\Event\ApiKeyCreated;
use App\Domain\Identity\ApiKey\Event\ApiKeyRevoked;
use App\Domain\Identity\ApiKey\Event\ApiKeyRotated;
use App\Domain\Identity\ApiKey\Exception\ApiKeyNotActive;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use App\Domain\Identity\ServiceAccount\ValueObject\Scope;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * Aggregate root for an API key — a long-lived, scoped credential for programmatic/external access.
 * The key is `unero_<env>_<prefix>.<secret>`: the prefix is public and indexed for O(1) lookup, the
 * secret is only ever held as a hash (shown once at creation). A key can expire, be revoked
 * (immediate, permanent), and records a throttled last-used timestamp. Verification is constant-time.
 */
final class ApiKey
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly ApiKeyId $id,
        private readonly ApiKeyPrefix $prefix,
        private readonly HashedApiKeySecret $secretHash,
        private readonly string $name,
        private readonly ApiKeyOwner $owner,
        private readonly ScopeCollection $scopes,
        private ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $lastUsedAt,
        private ?DateTimeImmutable $revokedAt,
        private readonly string $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        ApiKeyId $id,
        ApiKeyPrefix $prefix,
        HashedApiKeySecret $secretHash,
        string $name,
        ApiKeyOwner $owner,
        ScopeCollection $scopes,
        ?DateTimeImmutable $expiresAt,
        string $createdBy,
        DateTimeImmutable $now,
    ): self {
        $key = new self($id, $prefix, $secretHash, $name, $owner, $scopes, $expiresAt, null, null, $createdBy, $now, $now);

        $key->recordedEvents[] = new ApiKeyCreated(
            apiKeyId: $id->value,
            prefix: $prefix->value,
            name: $name,
            ownerType: $owner->type->value,
            ownerId: $owner->id,
            scopes: $scopes->toArray(),
            expiresAt: $expiresAt?->format(DATE_RFC3339),
            createdBy: $createdBy,
            occurredAt: $now->format(DATE_RFC3339),
        );

        return $key;
    }

    public static function reconstitute(
        ApiKeyId $id,
        ApiKeyPrefix $prefix,
        HashedApiKeySecret $secretHash,
        string $name,
        ApiKeyOwner $owner,
        ScopeCollection $scopes,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $lastUsedAt,
        ?DateTimeImmutable $revokedAt,
        string $createdBy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $prefix, $secretHash, $name, $owner, $scopes, $expiresAt, $lastUsedAt, $revokedAt, $createdBy, $createdAt, $updatedAt);
    }

    /** Revoke the key (idempotent). Revocation is immediate and permanent. */
    public function revoke(DateTimeImmutable $now): void
    {
        if ($this->revokedAt instanceof DateTimeImmutable) {
            return;
        }
        $this->revokedAt = $now;
        $this->updatedAt = $now;

        $this->recordedEvents[] = new ApiKeyRevoked($this->id->value, $now->format(DATE_RFC3339));
    }

    /**
     * Enter the rotation grace window: the key stays usable until $graceUntil (its remaining life is
     * capped — never extended — to that instant), after which it passively expires. A fresh key
     * ($replacementId) is issued separately by the caller. A revoked key cannot be rotated.
     */
    public function markRotated(ApiKeyId $replacementId, DateTimeImmutable $graceUntil, DateTimeImmutable $now): void
    {
        if ($this->revokedAt instanceof DateTimeImmutable) {
            throw ApiKeyNotActive::cannotRotate($this->id->value);
        }

        if (! $this->expiresAt instanceof DateTimeImmutable || $this->expiresAt > $graceUntil) {
            $this->expiresAt = $graceUntil;
        }
        $this->updatedAt = $now;

        $this->recordedEvents[] = new ApiKeyRotated($this->id->value, $replacementId->value, $now->format(DATE_RFC3339));
    }

    /** Constant-time verification of the presented secret against the stored hash. */
    public function verifySecret(string $presentedSecret): bool
    {
        return $this->secretHash->equals(HashedApiKeySecret::fromHash(hash('sha256', $presentedSecret)));
    }

    /**
     * Record that the key was used. Throttled: last_used_at only advances when it is unset or older
     * than $throttleSeconds, so a hot key does not write on every request. Returns true when the
     * timestamp advanced (the caller should then persist).
     */
    public function touch(DateTimeImmutable $now, int $throttleSeconds): bool
    {
        if ($this->lastUsedAt instanceof DateTimeImmutable && ($now->getTimestamp() - $this->lastUsedAt->getTimestamp()) < $throttleSeconds) {
            return false;
        }
        $this->lastUsedAt = $now;
        $this->updatedAt = $now;

        return true;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt <= $now;
    }

    /** Usable = not revoked and not expired. */
    public function isUsable(DateTimeImmutable $now): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired($now);
    }

    public function hasScope(Scope $scope): bool
    {
        return $this->scopes->contains($scope);
    }

    public function prefix(): ApiKeyPrefix
    {
        return $this->prefix;
    }

    public function secretHash(): HashedApiKeySecret
    {
        return $this->secretHash;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function owner(): ApiKeyOwner
    {
        return $this->owner;
    }

    public function scopes(): ScopeCollection
    {
        return $this->scopes;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<DomainEvent> Drains recorded events (called by the repository). */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
