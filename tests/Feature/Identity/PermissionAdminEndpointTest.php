<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PermissionAdminEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    public function test_define_then_list_includes_the_permission(): void
    {
        $this->postJson('/identity/permissions', ['name' => 'billing.refund', 'description' => 'Refund'], $this->admin + ['Idempotency-Key' => 'perm-1'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'billing.refund')
            ->assertJsonPath('data.resource', 'billing')
            ->assertJsonPath('data.action', 'refund')
            ->assertJsonPath('data.is_system', false);

        $this->getJson('/identity/permissions', $this->admin)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'billing.refund');
    }

    public function test_duplicate_permission_is_409(): void
    {
        $this->postJson('/identity/permissions', ['name' => 'billing.refund'], $this->admin + ['Idempotency-Key' => 'perm-1'])
            ->assertCreated();

        $this->postJson('/identity/permissions', ['name' => 'billing.refund'], $this->admin + ['Idempotency-Key' => 'perm-2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PERMISSION_002');
    }

    public function test_invalid_name_is_422(): void
    {
        $this->postJson('/identity/permissions', ['name' => 'NotValid'], $this->admin + ['Idempotency-Key' => 'perm-3'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }

    public function test_permission_surface_requires_actor(): void
    {
        $this->getJson('/identity/permissions')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }

    public function test_catalog_seeder_populates_system_permissions(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $response = $this->getJson('/identity/permissions', $this->admin)->assertOk();

        $names = [];
        foreach ((array) $response->json('data') as $row) {
            if (is_array($row) && isset($row['name'])) {
                $names[] = (string) $row['name'];
            }
        }

        $this->assertContains('user.create', $names);
        $this->assertContains('role.assign', $names);
        $this->assertCount(22, $names);
    }
}
