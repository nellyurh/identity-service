<?php

declare(strict_types=1);

namespace App\Domain\Identity\Permission;

use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Permission\ValueObject\PermissionName;

/**
 * A permission is reference data: a stable, immutable capability (`resource.action`) that
 * roles are granted. System permissions are seeded and cannot be redefined by tenants.
 */
final readonly class Permission
{
    public function __construct(
        public PermissionId $id,
        public PermissionName $name,
        public ?string $description,
        public bool $isSystem,
    ) {}

    public static function define(
        PermissionId $id,
        PermissionName $name,
        ?string $description,
        bool $isSystem,
    ): self {
        return new self($id, $name, $description, $isSystem);
    }
}
