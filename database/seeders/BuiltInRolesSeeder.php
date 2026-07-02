<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Seeds the built-in (system) roles and their permission grants. Runs after the permission
 * catalog (it grants catalogued permissions by name). Idempotent: existing roles are skipped.
 * "super_admin" is granted the entire catalog via the "*" sentinel so it stays complete as the
 * catalog grows.
 */
final class BuiltInRolesSeeder extends Seeder
{
    /** @var array<string,array{description:string,permissions:list<string>}> */
    private const array ROLES = [
        'super_admin' => [
            'description' => 'Full platform administration.',
            'permissions' => ['*'],
        ],
        'platform_admin' => [
            'description' => 'Administers users, roles, service accounts and keys.',
            'permissions' => [
                'user.create', 'user.read', 'user.update', 'user.disable', 'user.delete',
                'role.create', 'role.read', 'role.update', 'role.delete', 'role.assign',
                'permission.read',
                'serviceaccount.create', 'serviceaccount.read', 'serviceaccount.update', 'serviceaccount.disable',
                'apikey.create', 'apikey.read', 'apikey.revoke',
                'token.introspect', 'token.revoke', 'audit.read',
            ],
        ],
        'finance' => [
            'description' => 'Finance operations (identity-scoped: read users, read audit).',
            'permissions' => ['user.read', 'audit.read'],
        ],
        'compliance' => [
            'description' => 'Compliance and oversight.',
            'permissions' => ['user.read', 'audit.read'],
        ],
        'support' => [
            'description' => 'Customer support: read users, disable/enable accounts.',
            'permissions' => ['user.read', 'user.disable'],
        ],
        'developer' => [
            'description' => 'Programmatic access: API keys and token introspection.',
            'permissions' => ['apikey.create', 'apikey.read', 'apikey.revoke', 'serviceaccount.read', 'token.introspect'],
        ],
        'read_only' => [
            'description' => 'Read-only visibility across identity resources.',
            'permissions' => ['user.read', 'role.read', 'permission.read', 'serviceaccount.read', 'apikey.read', 'audit.read'],
        ],
        'service' => [
            'description' => 'Default role for internal service principals.',
            'permissions' => ['token.introspect'],
        ],
    ];

    public function run(): void
    {
        $roles = app(RoleRepository::class);
        $permissions = app(PermissionRepository::class);
        $now = Date::now()->toImmutable();

        foreach (self::ROLES as $slug => $spec) {
            $name = new RoleName($slug);
            if ($roles->existsByName($name)) {
                continue;
            }

            $role = Role::create($roles->nextIdentity(), $name, $spec['description'], true, $now);

            $grants = $spec['permissions'] === ['*'] ? $this->allPermissionNames($permissions) : $spec['permissions'];
            foreach ($grants as $permissionName) {
                $role->grantPermission($permissions->getByName(new PermissionName($permissionName))->id, $now);
            }

            $roles->save($role);
        }
    }

    /**
     * @return list<string>
     */
    private function allPermissionNames(PermissionRepository $permissions): array
    {
        return array_map(static fn (Permission $p): string => $p->name->value, $permissions->all());
    }
}
