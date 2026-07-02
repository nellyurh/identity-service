<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when an API key is revoked. Revocation is immediate and permanent. */
final readonly class ApiKeyRevoked implements DomainEvent
{
    public function __construct(
        public string $apiKeyId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'ApiKeyRevoked';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'api_key_id' => $this->apiKeyId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
