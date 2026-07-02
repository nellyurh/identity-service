<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Role\Repository\RoleRepository;

/** Read-side: all roles (name-ordered) with their resolved permission names. */
final readonly class ListRoles
{
    public function __construct(
        private RoleRepository $roles,
        private RoleViewFactory $views,
    ) {}

    /** @return list<RoleView> */
    public function handle(): array
    {
        return $this->views->makeMany($this->roles->all());
    }
}
