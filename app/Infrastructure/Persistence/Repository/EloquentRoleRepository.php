<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Role\Exception\RoleNotFound;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;
use App\Infrastructure\Persistence\Model\RoleModel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * Eloquent adapter for RoleRepository. The role row and its role_permissions pivot are written
 * together (the pivot is fully re-synced from the aggregate on each save), so the persisted grant
 * set always matches the aggregate. Timestamps are managed here — the Role aggregate does not carry
 * them. save() must run inside the caller's transaction.
 */
final readonly class EloquentRoleRepository implements RoleRepository
{
    public function findById(RoleId $id): ?Role
    {
        return $this->map(RoleModel::query()->find($id->value));
    }

    public function findByName(RoleName $name): ?Role
    {
        return $this->map(RoleModel::query()->where('name', $name->value)->first());
    }

    public function getById(RoleId $id): Role
    {
        return $this->findById($id) ?? throw RoleNotFound::withId($id->value);
    }

    public function existsByName(RoleName $name): bool
    {
        return RoleModel::query()->where('name', $name->value)->exists();
    }

    /** @return list<Role> */
    public function all(): array
    {
        return array_values(
            RoleModel::query()
                ->orderBy('name')
                ->get()
                ->map(fn (RoleModel $model): Role => $this->hydrate($model))
                ->all(),
        );
    }

    public function save(Role $role): void
    {
        $model = RoleModel::query()->firstOrNew(['id' => $role->id->value]);
        $now = Date::now()->toImmutable();

        if (! $model->exists) {
            $model->created_at = $now;
        }

        $model->id = $role->id->value;
        $model->name = $role->name()->value;
        $model->description = $role->description();
        $model->is_system = $role->isSystem();
        $model->updated_at = $now;
        $model->save();

        DB::table('role_permissions')->where('role_id', $role->id->value)->delete();

        $rows = [];
        foreach ($role->permissions() as $permissionId) {
            $rows[] = [
                'role_id' => $role->id->value,
                'permission_id' => $permissionId->value,
                'created_at' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('role_permissions')->insert($rows);
        }
    }

    public function nextIdentity(): RoleId
    {
        return new RoleId((string) new Ulid);
    }

    private function map(?RoleModel $model): ?Role
    {
        if (! $model instanceof RoleModel) {
            return null;
        }

        return $this->hydrate($model);
    }

    private function hydrate(RoleModel $model): Role
    {
        return Role::reconstitute(
            new RoleId($model->id),
            new RoleName($model->name),
            $model->description,
            $model->is_system,
            $this->permissionIds($model->id),
        );
    }

    /** @return list<PermissionId> */
    private function permissionIds(string $roleId): array
    {
        $ids = [];
        foreach (DB::table('role_permissions')->where('role_id', $roleId)->pluck('permission_id') as $permissionId) {
            $ids[] = new PermissionId((string) $permissionId);
        }

        return $ids;
    }
}
