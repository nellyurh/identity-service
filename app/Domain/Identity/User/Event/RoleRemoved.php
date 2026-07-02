<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a role is revoked from a user. Carries the user's new authz_version so consumers
 * can invalidate cached authorization. No PII.
 * Matches unero-shared-schemas/schemas/events/RoleRemoved.schema.json.
 */
final readonly class RoleRemoved implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $roleId,
        public int $authzVersion,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'RoleRemoved';
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
