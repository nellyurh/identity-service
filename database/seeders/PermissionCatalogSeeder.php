<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use Illuminate\Database\Seeder;

/**
 * Seeds identity-service's canonical, system permission catalog (resource.action). System
 * permissions are the platform's own access vocabulary — seeded, not tenant-defined. Idempotent:
 * re-running skips permissions that already exist, so it is safe on every deploy.
 */
final class PermissionCatalogSeeder extends Seeder
{
    /** @var list<array{name:string,description:string}> */
    private const array CATALOG = [
        ['name' => 'user.create', 'description' => 'Create users'],
        ['name' => 'user.read', 'description' => 'Read users'],
        ['name' => 'user.update', 'description' => 'Update users'],
        ['name' => 'user.disable', 'description' => 'Disable or enable users'],
        ['name' => 'user.delete', 'description' => 'Soft-delete users'],
        ['name' => 'role.create', 'description' => 'Create roles'],
        ['name' => 'role.read', 'description' => 'Read roles'],
        ['name' => 'role.update', 'description' => 'Update roles and their permissions'],
        ['name' => 'role.delete', 'description' => 'Delete roles'],
        ['name' => 'role.assign', 'description' => 'Assign or revoke roles on users'],
        ['name' => 'permission.read', 'description' => 'Read the permission catalog'],
        ['name' => 'permission.create', 'description' => 'Define new permissions'],
        ['name' => 'apikey.create', 'description' => 'Create API keys'],
        ['name' => 'apikey.read', 'description' => 'Read API key metadata'],
        ['name' => 'apikey.revoke', 'description' => 'Revoke API keys'],
        ['name' => 'serviceaccount.create', 'description' => 'Create service accounts'],
        ['name' => 'serviceaccount.read', 'description' => 'Read service accounts'],
        ['name' => 'serviceaccount.update', 'description' => 'Update service accounts'],
        ['name' => 'serviceaccount.disable', 'description' => 'Disable service accounts'],
        ['name' => 'token.introspect', 'description' => 'Introspect access tokens'],
        ['name' => 'token.revoke', 'description' => 'Revoke tokens'],
        ['name' => 'audit.read', 'description' => 'Read audit events'],
    ];

    public function run(): void
    {
        $permissions = app(PermissionRepository::class);

        foreach (self::CATALOG as $entry) {
            $name = new PermissionName($entry['name']);
            if ($permissions->existsByName($name)) {
                continue;
            }

            $permissions->save(Permission::define(
                $permissions->nextIdentity(),
                $name,
                $entry['description'],
                true,
            ));
        }
    }
}
