<?php

declare(strict_types=1);

namespace App\Domain\Identity\Permission\Repository;

use App\Domain\Identity\Permission\Exception\PermissionNotFound;
use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Permission\ValueObject\PermissionName;

interface PermissionRepository
{
    public function findById(PermissionId $id): ?Permission;

    public function findByName(PermissionName $name): ?Permission;

    /** @throws PermissionNotFound */
    public function getByName(PermissionName $name): Permission;

    public function existsByName(PermissionName $name): bool;

    /** @return list<Permission> */
    public function all(): array;

    public function save(Permission $permission): void;

    public function nextIdentity(): PermissionId;
}
