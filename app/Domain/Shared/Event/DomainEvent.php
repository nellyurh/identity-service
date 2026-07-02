<?php

declare(strict_types=1);

namespace App\Domain\Shared\Event;

/**
 * Contract every domain event satisfies so the repository can drain recorded events into
 * the outbox uniformly (Outbox Pattern). `eventType()` is the PascalCase name that matches
 * the shared event-envelope contract (unero-shared-schemas); `payload()` is the event body
 * that must validate against that event type's payload schema.
 */
interface DomainEvent
{
    public function eventType(): string;

    /** @return array<string,mixed> */
    public function payload(): array;
}
