<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a refresh-token family is revoked — on logout, on reuse detection, or when a
 * security-relevant change (e.g. password change) invalidates existing sessions. Family-level:
 * one event announces the whole lineage is dead. The reason is a stable machine value so
 * consumers can react (e.g. force re-auth) without parsing prose.
 * Matches unero-shared-schemas/schemas/events/TokenRevoked.schema.json.
 */
final readonly class TokenRevoked implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $familyId,
        public string $reason,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'TokenRevoked';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'family_id' => $this->familyId,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
