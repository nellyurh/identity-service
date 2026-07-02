<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a service account is disabled and can no longer authenticate. */
final readonly class ServiceAccountDisabled implements DomainEvent
{
    public function __construct(
        public string $serviceAccountId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'ServiceAccountDisabled';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'service_account_id' => $this->serviceAccountId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
