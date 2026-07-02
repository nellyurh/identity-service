<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\Event;

use App\Domain\Shared\Event\DomainEvent;

/** Emitted when a service account's client secret is rotated. Never carries the secret or its hash. */
final readonly class ServiceAccountCredentialRotated implements DomainEvent
{
    public function __construct(
        public string $serviceAccountId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'ServiceAccountCredentialRotated';
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
