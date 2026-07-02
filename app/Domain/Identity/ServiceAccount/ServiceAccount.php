<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount;

use App\Domain\Identity\ServiceAccount\Event\ServiceAccountCreated;
use App\Domain\Identity\ServiceAccount\Event\ServiceAccountCredentialRotated;
use App\Domain\Identity\ServiceAccount\Event\ServiceAccountDisabled;
use App\Domain\Identity\ServiceAccount\Exception\ServiceAccountNotActive;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\Scope;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * Aggregate root for a service account — the identity a platform service (billing, wallet,
 * gateway, notification, storage) uses to authenticate to others. Replaces shared secrets:
 * each service holds its own rotatable, scoped credential whose secret is only ever stored
 * as a hash.
 */
final class ServiceAccount
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly ServiceAccountId $id,
        private readonly ServiceName $name,
        private HashedSecret $secretHash,
        private ServiceAccountStatus $status,
        private readonly ScopeCollection $scopes,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        ServiceAccountId $id,
        ServiceName $name,
        HashedSecret $secretHash,
        ScopeCollection $scopes,
        DateTimeImmutable $now,
    ): self {
        $account = new self($id, $name, $secretHash, ServiceAccountStatus::Active, $scopes, $now, $now);

        $account->recordedEvents[] = new ServiceAccountCreated(
            serviceAccountId: $id->value,
            name: $name->value,
            scopes: $scopes->toArray(),
            occurredAt: $now->format(DATE_RFC3339),
        );

        return $account;
    }

    public static function reconstitute(
        ServiceAccountId $id,
        ServiceName $name,
        HashedSecret $secretHash,
        ServiceAccountStatus $status,
        ScopeCollection $scopes,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $name, $secretHash, $status, $scopes, $createdAt, $updatedAt);
    }

    public function rotateSecret(HashedSecret $newSecretHash, DateTimeImmutable $now): void
    {
        $this->secretHash = $newSecretHash;
        $this->updatedAt = $now;

        $this->recordedEvents[] = new ServiceAccountCredentialRotated($this->id->value, $now->format(DATE_RFC3339));
    }

    public function disable(DateTimeImmutable $now): void
    {
        if ($this->status === ServiceAccountStatus::Disabled) {
            return;
        }
        $this->status = ServiceAccountStatus::Disabled;
        $this->updatedAt = $now;

        $this->recordedEvents[] = new ServiceAccountDisabled($this->id->value, $now->format(DATE_RFC3339));
    }

    public function assertCanAuthenticate(): void
    {
        if (! $this->status->canAuthenticate()) {
            throw ServiceAccountNotActive::because($this->status);
        }
    }

    public function hasScope(Scope $scope): bool
    {
        return $this->scopes->contains($scope);
    }

    public function name(): ServiceName
    {
        return $this->name;
    }

    public function secretHash(): HashedSecret
    {
        return $this->secretHash;
    }

    public function status(): ServiceAccountStatus
    {
        return $this->status;
    }

    public function scopes(): ScopeCollection
    {
        return $this->scopes;
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
