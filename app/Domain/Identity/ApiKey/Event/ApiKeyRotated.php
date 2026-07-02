<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when an API key is rotated: this key enters its grace window (capped expiry) and a
 * replacement key is issued. Carries the replacement's id but never any secret.
 */
final readonly class ApiKeyRotated implements DomainEvent
{
    public function __construct(
        public string $apiKeyId,
        public string $replacementId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'ApiKeyRotated';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'api_key_id' => $this->apiKeyId,
            'replacement_id' => $this->replacementId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
