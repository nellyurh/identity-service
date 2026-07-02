<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Domain\Identity\Role\Exception\RoleNotFound;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\Role\ValueObject\RoleName;
use App\Infrastructure\Persistence\Repository\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Repository\EloquentRoleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentRoleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentRoleRepository $roles;

    private EloquentPermissionRepository $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roles = new EloquentRoleRepository;
        $this->permissions = new EloquentPermissionRepository;
    }

    private function permission(string $name): Permission
    {
        $permission = Permission::define($this->permissions->nextIdentity(), new PermissionName($name), null, true);
        $this->permissions->save($permission);

        return $permission;
    }

    public function test_save_persists_role_and_permission_pivot(): void
    {
        $read = $this->permission('user.read');
        $create = $this->permission('user.create');

        $role = Role::create($this->roles->nextIdentity(), new RoleName('editor'), 'Editors', false);
        $role->grantPermission($read->id);
        $role->grantPermission($create->id);
        $this->roles->save($role);

        $this->assertDatabaseHas('roles', ['id' => $role->id->value, 'name' => 'editor', 'is_system' => false]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id->value, 'permission_id' => $read->id->value]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id->value, 'permission_id' => $create->id->value]);

        $loaded = $this->roles->findById(new RoleId($role->id->value));
        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded?->permissions() ?? []);
    }

    public function test_resave_syncs_the_pivot(): void
    {
        $read = $this->permission('user.read');
        $create = $this->permission('user.create');

        $role = Role::create($this->roles->nextIdentity(), new RoleName('editor'), null, false);
        $role->grantPermission($read->id);
        $role->grantPermission($create->id);
        $this->roles->save($role);

        $loaded = $this->roles->getById(new RoleId($role->id->value));
        $loaded->revokePermission($read->id);
        $this->roles->save($loaded);

        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id->value, 'permission_id' => $read->id->value]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id->value, 'permission_id' => $create->id->value]);
    }

    public function test_all_is_name_ordered(): void
    {
        $this->roles->save(Role::create($this->roles->nextIdentity(), new RoleName('zulu'), null, false));
        $this->roles->save(Role::create($this->roles->nextIdentity(), new RoleName('alpha'), null, false));

        $names = array_map(static fn (Role $r): string => $r->name()->value, $this->roles->all());

        $this->assertSame(['alpha', 'zulu'], $names);
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(RoleNotFound::class);
        $this->roles->getById(new RoleId((string) new Ulid));
    }
}
