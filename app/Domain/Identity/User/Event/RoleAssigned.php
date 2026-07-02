<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a role is assigned to a user. Carries the user's new authz_version so consumers
 * (and the user's own future tokens) can tell that cached authorization for this user is stale.
 * No PII. Matches unero-shared-schemas/schemas/events/RoleAssigned.schema.json.
 */
final readonly class RoleAssigned implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $roleId,
        public int $authzVersion,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'RoleAssigned';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'role_id' => $this->roleId,
            'authz_version' => $this->authzVersion,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
