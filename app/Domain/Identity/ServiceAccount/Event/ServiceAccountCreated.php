<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a service account is provisioned. Never carries the secret or its hash. */
final readonly class ServiceAccountCreated implements DomainEvent
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $serviceAccountId,
        public string $name,
        public array $scopes,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'ServiceAccountCreated';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'service_account_id' => $this->serviceAccountId,
            'name' => $this->name,
            'scopes' => $this->scopes,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
