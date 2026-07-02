<?php

declare(strict_types=1);

namespace App\Application\Role;

use App\Application\Role\Result\RoleView;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\ValueObject\RoleId;

/** Read a single role by id (throws RoleNotFound if absent). */
final readonly class GetRole
{
    public function __construct(
        private RoleRepository $roles,
        private RoleViewFactory $views,
    ) {}

    public function handle(string $roleId): RoleView
    {
        return $this->views->make($this->roles->getById(new RoleId($roleId)));
    }
}
