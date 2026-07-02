<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class UserRoleEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function newUserId(): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');
    }

    private function newRoleId(string $name): string
    {
        return (string) $this->postJson('/identity/roles', ['name' => $name], $this->admin + ['Idempotency-Key' => 'role-'.$name])
            ->assertCreated()->json('data.id');
    }

    public function test_assign_then_revoke_role(): void
    {
        $userId = $this->newUserId();
        $roleId = $this->newRoleId('editor');

        $this->postJson("/identity/users/{$userId}/roles", ['role_id' => $roleId], $this->admin + ['Idempotency-Key' => 'a1'])
            ->assertOk()
            ->assertJsonPath('data.roles', [$roleId])
            ->assertJsonPath('data.authz_version', 2);

        $this->deleteJson("/identity/users/{$userId}/roles/{$roleId}", [], $this->admin + ['Idempotency-Key' => 'r1'])
            ->assertOk()
            ->assertJsonPath('data.roles', [])
            ->assertJsonPath('data.authz_version', 3);
    }

    public function test_assigning_same_role_again_is_idempotent(): void
    {
        $userId = $this->newUserId();
        $roleId = $this->newRoleId('editor');

        $this->postJson("/identity/users/{$userId}/roles", ['role_id' => $roleId], $this->admin + ['Idempotency-Key' => 'a1'])
            ->assertOk()->assertJsonPath('data.authz_version', 2);

        $this->postJson("/identity/users/{$userId}/roles", ['role_id' => $roleId], $this->admin + ['Idempotency-Key' => 'a2'])
            ->assertOk()->assertJsonPath('data.authz_version', 2);
    }

    public function test_assign_unknown_role_is_404(): void
    {
        $userId = $this->newUserId();

        $this->postJson("/identity/users/{$userId}/roles", ['role_id' => (string) new Ulid], $this->admin + ['Idempotency-Key' => 'a1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'ROLE_001');
    }

    public function test_assign_to_unknown_user_is_404(): void
    {
        $roleId = $this->newRoleId('editor');

        $this->postJson('/identity/users/'.new Ulid.'/roles', ['role_id' => $roleId], $this->admin + ['Idempotency-Key' => 'a1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_001');
    }
}
