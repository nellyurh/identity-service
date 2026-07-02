<?php

declare(strict_types=1);

namespace App\Application\Permission;

use App\Application\Permission\Result\PermissionView;
use App\Domain\Identity\Permission\Repository\PermissionRepository;

/** Read-side: the full permission catalog, name-ordered, as view DTOs. */
final readonly class ListPermissions
{
    public function __construct(private PermissionRepository $permissions) {}

    /** @return list<PermissionView> */
    public function handle(): array
    {
        return array_map(
            PermissionView::fromPermission(...),
            $this->permissions->all(),
        );
    }
}
