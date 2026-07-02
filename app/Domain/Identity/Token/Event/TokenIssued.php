<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a refresh token is issued — on login (new family) and on each rotation
 * (same family, new member). Carries no secret: only the user and family ids plus the
 * paired access-token jti, so consumers can correlate sessions without ever seeing the
 * token. Matches unero-shared-schemas/schemas/events/TokenIssued.schema.json.
 */
final readonly class TokenIssued implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $familyId,
        public string $accessJti,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'TokenIssued';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'family_id' => $this->familyId,
            'access_jti' => $this->accessJti,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
