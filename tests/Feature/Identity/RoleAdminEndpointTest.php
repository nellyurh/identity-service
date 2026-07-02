<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Database\Seeders\BuiltInRolesSeeder;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RoleAdminEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
    }

    /** @param array<string,string> $extra */
    private function headers(array $extra = []): array
    {
        return $this->admin + $extra;
    }

    private function createRole(string $name): string
    {
        return (string) $this->postJson('/identity/roles', ['name' => $name, 'description' => 'x'], $this->headers(['Idempotency-Key' => 'role-'.$name]))
            ->assertCreated()->json('data.id');
    }

    private function roleId(string $name): string
    {
        foreach ((array) $this->getJson('/identity/roles', $this->admin)->json('data') as $row) {
            if (is_array($row) && ($row['name'] ?? null) === $name) {
                return (string) $row['id'];
            }
        }

        return '';
    }

    public function test_create_role(): void
    {
        $this->postJson('/identity/roles', ['name' => 'editor', 'description' => 'Editors'], $this->headers(['Idempotency-Key' => 'r1']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'editor')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions', []);
    }

    public function test_grant_then_revoke_permission(): void
    {
        $id = $this->createRole('editor');

        $this->postJson("/identity/roles/{$id}/permissions", ['permission' => 'user.read'], $this->headers(['Idempotency-Key' => 'g1']))
            ->assertOk()
            ->assertJsonPath('data.permissions', ['user.read']);

        $this->deleteJson("/identity/roles/{$id}/permissions/user.read", [], $this->headers(['Idempotency-Key' => 'rv1']))
            ->assertOk()
            ->assertJsonPath('data.permissions', []);
    }

    public function test_list_and_show(): void
    {
        $id = $this->createRole('editor');

        $this->getJson('/identity/roles', $this->admin)->assertOk();
        $this->getJson("/identity/roles/{$id}", $this->admin)
            ->assertOk()
            ->assertJsonPath('data.name', 'editor');
    }

    public function test_update_renames_role(): void
    {
        $id = $this->createRole('editor');

        $this->patchJson("/identity/roles/{$id}", ['name' => 'authors'], $this->headers(['Idempotency-Key' => 'u1']))
            ->assertOk()
            ->assertJsonPath('data.name', 'authors');
    }

    public function test_duplicate_name_is_409(): void
    {
        $this->createRole('editor');

        $this->postJson('/identity/roles', ['name' => 'editor'], $this->headers(['Idempotency-Key' => 'dup']))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ROLE_003');
    }

    public function test_grant_unknown_permission_is_404(): void
    {
        $id = $this->createRole('editor');

        $this->postJson("/identity/roles/{$id}/permissions", ['permission' => 'ghost.action'], $this->headers(['Idempotency-Key' => 'g404']))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PERMISSION_001');
    }

    public function test_renaming_a_system_role_is_403(): void
    {
        $this->seed(BuiltInRolesSeeder::class);
        $id = $this->roleId('super_admin');

        $this->patchJson("/identity/roles/{$id}", ['name' => 'root'], $this->headers(['Idempotency-Key' => 'sys']))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ROLE_002');
    }

    public function test_role_surface_requires_actor(): void
    {
        $this->getJson('/identity/roles')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }

    public function test_builtin_super_admin_holds_the_whole_catalog(): void
    {
        $this->seed(BuiltInRolesSeeder::class);
        $id = $this->roleId('super_admin');

        $this->getJson("/identity/roles/{$id}", $this->admin)
            ->assertOk()
            ->assertJsonPath('data.is_system', true)
            ->assertJsonCount(22, 'data.permissions');
    }
}
