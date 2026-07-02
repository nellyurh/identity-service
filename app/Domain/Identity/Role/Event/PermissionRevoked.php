<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\Event;

use App\Domain\Shared\Event\DomainEvent;

/**
 * Emitted when a permission is revoked from a role. Reference data, no PII.
 * Matches unero-shared-schemas/schemas/events/PermissionRevoked.schema.json.
 */
final readonly class PermissionRevoked implements DomainEvent
{
    public function __construct(
        public string $roleId,
        public string $permissionId,
        public string $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'PermissionRevoked';
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'role_id' => $this->roleId,
            'permission_id' => $this->permissionId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
