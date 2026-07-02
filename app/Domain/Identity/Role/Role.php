<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role;

use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Role\Event\PermissionGranted;
use App\Domain\Identity\Role\Event\PermissionRevoked;
use App\Domain\Identity\Role\Event\RoleCreated;
use App\Domain\Identity\Role\Exception\SystemRoleImmutable;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * Aggregate root for a role: a named set of permissions that users (and service accounts)
 * are granted. Built-in "system" roles (super_admin, member, service, ...) are protected —
 * they can hold permissions but cannot be renamed or deleted, so the platform's own access
 * model can't be broken by tenant edits.
 */
final class Role
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @param list<PermissionId> $permissions */
    private function __construct(public readonly RoleId $id, private RoleName $name, private ?string $description, private readonly bool $isSystem, private array $permissions) {}

    public static function create(
        RoleId $id,
        RoleName $name,
        ?string $description,
        bool $isSystem,
        DateTimeImmutable $now,
    ): self {
        $role = new self($id, $name, $description, $isSystem, []);

        $role->recordedEvents[] = new RoleCreated($id->value, $name->value, $isSystem, $now->format(DATE_RFC3339));

        return $role;
    }

    /** @param list<PermissionId> $permissions */
    public static function reconstitute(
        RoleId $id,
        RoleName $name,
        ?string $description,
        bool $isSystem,
        array $permissions,
    ): self {
        return new self($id, $name, $description, $isSystem, $permissions);
    }

    public function rename(RoleName $name): void
    {
        if ($this->isSystem) {
            throw SystemRoleImmutable::forName($this->name->value);
        }
        $this->name = $name;
    }

    public function describe(?string $description): void
    {
        $this->description = $description;
    }

    public function grantPermission(PermissionId $permissionId, DateTimeImmutable $now): void
    {
        if ($this->hasPermission($permissionId)) {
            return;
        }
        $this->permissions[] = $permissionId;

        $this->recordedEvents[] = new PermissionGranted($this->id->value, $permissionId->value, $now->format(DATE_RFC3339));
    }

    public function revokePermission(PermissionId $permissionId, DateTimeImmutable $now): void
    {
        if (! $this->hasPermission($permissionId)) {
            return;
        }
        $this->permissions = array_values(array_filter(
            $this->permissions,
            static fn (PermissionId $p): bool => ! $p->equals($permissionId),
        ));

        $this->recordedEvents[] = new PermissionRevoked($this->id->value, $permissionId->value, $now->format(DATE_RFC3339));
    }

    public function hasPermission(PermissionId $permissionId): bool
    {
        foreach ($this->permissions as $p) {
            if ($p->equals($permissionId)) {
                return true;
            }
        }

        return false;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    /** @return list<PermissionId> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /** @return list<DomainEvent> Drains recorded events (called by the repository). */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
