<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Permission\Exception\PermissionNotFound;
use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\ValueObject\PermissionId;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Infrastructure\Persistence\Model\PermissionModel;
use App\Infrastructure\Persistence\Repository\EloquentPermissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentPermissionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): EloquentPermissionRepository
    {
        return new EloquentPermissionRepository;
    }

    private function define(EloquentPermissionRepository $repo, string $name, bool $system = true): Permission
    {
        $permission = Permission::define($repo->nextIdentity(), new PermissionName($name), 'desc', $system);
        $repo->save($permission);

        return $permission;
    }

    public function test_save_persists_with_denormalised_resource_action(): void
    {
        $repo = $this->repo();
        $permission = $this->define($repo, 'wallet.credit');

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id->value,
            'name' => 'wallet.credit',
            'resource' => 'wallet',
            'action' => 'credit',
            'is_system' => true,
        ]);
    }

    public function test_find_by_name_and_id_round_trip(): void
    {
        $repo = $this->repo();
        $permission = $this->define($repo, 'user.create');

        $byName = $repo->findByName(new PermissionName('user.create'));
        $byId = $repo->findById(new PermissionId($permission->id->value));

        $this->assertNotNull($byName);
        $this->assertNotNull($byId);
        $this->assertSame('user.create', $byName?->name->value);
        $this->assertSame($permission->id->value, $byId?->id->value);
    }

    public function test_save_is_idempotent_upsert(): void
    {
        $repo = $this->repo();
        $permission = $this->define($repo, 'role.read');

        // Re-save the same id with a changed description.
        $repo->save(Permission::define(
            new PermissionId($permission->id->value),
            new PermissionName('role.read'),
            'updated',
            true,
        ));

        $this->assertSame(1, PermissionModel::query()->count());
    }

    public function test_all_returns_name_ordered_catalog(): void
    {
        $repo = $this->repo();
        $this->define($repo, 'user.read');
        $this->define($repo, 'audit.read');
        $this->define($repo, 'role.read');

        $names = array_map(static fn (Permission $p): string => $p->name->value, $repo->all());

        $this->assertSame(['audit.read', 'role.read', 'user.read'], $names);
    }

    public function test_get_by_name_throws_when_missing(): void
    {
        $this->expectException(PermissionNotFound::class);
        $this->repo()->getByName(new PermissionName('ghost.action'));
    }

    public function test_exists_by_name(): void
    {
        $repo = $this->repo();
        $this->define($repo, 'token.revoke');

        $this->assertTrue($repo->existsByName(new PermissionName('token.revoke')));
        $this->assertFalse($repo->existsByName(new PermissionName('token.introspect')));
    }
}
