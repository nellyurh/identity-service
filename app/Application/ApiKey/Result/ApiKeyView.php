<?php

declare(strict_types=1);

namespace App\Application\ApiKey\Result;

use App\Domain\Identity\ApiKey\ApiKey;

/** A safe projection of an API key — everything except the secret. */
final readonly class ApiKeyView
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $prefix,
        public string $name,
        public string $ownerType,
        public string $ownerId,
        public array $scopes,
        public ?string $expiresAt,
        public ?string $lastUsedAt,
        public ?string $revokedAt,
        public bool $revoked,
        public string $createdBy,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromKey(ApiKey $key): self
    {
        return new self(
            id: $key->id->value,
            prefix: $key->prefix()->value,
            name: $key->name(),
            ownerType: $key->owner()->type->value,
            ownerId: $key->owner()->id,
            scopes: $key->scopes()->toArray(),
            expiresAt: $key->expiresAt()?->format(DATE_RFC3339),
            lastUsedAt: $key->lastUsedAt()?->format(DATE_RFC3339),
            revokedAt: $key->revokedAt()?->format(DATE_RFC3339),
            revoked: $key->isRevoked(),
            createdBy: $key->createdBy(),
            createdAt: $key->createdAt()->format(DATE_RFC3339),
            updatedAt: $key->updatedAt()->format(DATE_RFC3339),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prefix' => $this->prefix,
            'name' => $this->name,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
            'last_used_at' => $this->lastUsedAt,
            'revoked_at' => $this->revokedAt,
            'revoked' => $this->revoked,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
