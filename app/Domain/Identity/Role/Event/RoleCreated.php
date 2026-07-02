<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a role is created (via the admin API or platform seeding). Reference data, no PII.
 * Matches unero-shared-schemas/schemas/events/RoleCreated.schema.json.
 */
final readonly class RoleCreated implements DomainEvent
{
    public function __construct(
        public string $roleId,
        public string $name,
        public bool $isSystem,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'RoleCreated';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'role_id' => $this->roleId,
            'name' => $this->name,
            'is_system' => $this->isSystem,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
