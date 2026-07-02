<?php

declare(strict_types=1);

namespace App\Domain\Identity\Role\Repository;

use App\Domain\Identity\Role\Exception\RoleNotFound;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;

interface RoleRepository
{
    public function findById(RoleId $id): ?Role;

    public function findByName(RoleName $name): ?Role;

    /** @throws RoleNotFound */
    public function getById(RoleId $id): Role;

    /** @return list<Role> */
    public function all(): array;

    public function existsByName(RoleName $name): bool;

    public function save(Role $role): void;

    public function nextIdentity(): RoleId;
}
