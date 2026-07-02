<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Permission\Exception\PermissionNotFound;
use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Infrastructure\Persistence\Model\PermissionModel;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Uid\Ulid;

/**
 * Eloquent adapter for PermissionRepository. Permissions are reference data (no domain events),
 * so save() is a plain upsert. The resource/action columns are denormalised from the name for
 * indexed lookups; the aggregate derives them from the name on the way back in.
 */
final readonly class EloquentPermissionRepository implements PermissionRepository
{
    public function findById(PermissionId $id): ?Permission
    {
        return $this->map(PermissionModel::query()->find($id->value));
    }

    public function findByName(PermissionName $name): ?Permission
    {
        return $this->map(PermissionModel::query()->where('name', $name->value)->first());
    }

    public function getByName(PermissionName $name): Permission
    {
        return $this->findByName($name) ?? throw PermissionNotFound::withName($name->value);
    }

    public function existsByName(PermissionName $name): bool
    {
        return PermissionModel::query()->where('name', $name->value)->exists();
    }

    /** @return list<Permission> */
    public function all(): array
    {
        return array_values(
            PermissionModel::query()
                ->orderBy('name')
                ->get()
                ->map(fn (PermissionModel $model): Permission => $this->hydrate($model))
                ->all(),
        );
    }

    public function save(Permission $permission): void
    {
        $model = PermissionModel::query()->firstOrNew(['id' => $permission->id->value]);
        $now = Date::now()->toImmutable();

        if (! $model->exists) {
            $model->created_at = $now;
        }

        $model->id = $permission->id->value;
        $model->name = $permission->name->value;
        $model->resource = $permission->name->resource;
        $model->action = $permission->name->action;
        $model->description = $permission->description;
        $model->is_system = $permission->isSystem;
        $model->updated_at = $now;
        $model->save();
    }

    public function nextIdentity(): PermissionId
    {
        return new PermissionId((string) new Ulid);
    }

    private function map(?PermissionModel $model): ?Permission
    {
        if (! $model instanceof PermissionModel) {
            return null;
        }

        return $this->hydrate($model);
    }

    private function hydrate(PermissionModel $model): Permission
    {
        return Permission::define(
            new PermissionId($model->id),
            new PermissionName($model->name),
            $model->description,
            $model->is_system,
        );
    }
}
