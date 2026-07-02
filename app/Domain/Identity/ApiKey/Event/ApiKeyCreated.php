<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when an API key is created. Carries the public prefix and metadata but never the secret
 * or its hash. Matches unero-shared-schemas/schemas/events/ApiKeyCreated.schema.json.
 */
final readonly class ApiKeyCreated implements DomainEvent
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $apiKeyId,
        public string $prefix,
        public string $name,
        public string $ownerType,
        public string $ownerId,
        public array $scopes,
        public ?string $expiresAt,
        public string $createdBy,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'ApiKeyCreated';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'api_key_id' => $this->apiKeyId,
            'prefix' => $this->prefix,
            'name' => $this->name,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
            'created_by' => $this->createdBy,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
