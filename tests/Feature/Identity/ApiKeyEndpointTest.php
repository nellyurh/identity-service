<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class ApiKeyEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    private function ownerServiceAccountId(): string
    {
        return (string) $this->postJson('/identity/service-accounts', [
            'name' => 'wallet', 'scopes' => ['wallet.credit'],
        ], $this->admin + ['Idempotency-Key' => 'sa1'])->assertCreated()->json('data.id');
    }

    /** @return array{id:string,key:string,owner:string} */
    private function createKey(string $idem = 'k1'): array
    {
        $owner = $this->ownerServiceAccountId();

        $data = $this->postJson('/identity/api-keys', [
            'name' => 'CI deploy',
            'owner_type' => 'service_account',
            'owner_id' => $owner,
            'scopes' => ['wallet.read'],
        ], $this->admin + ['Idempotency-Key' => $idem])
            ->assertCreated()
            ->assertJsonPath('data.owner_type', 'service_account')
            ->assertJsonMissingPath('data.secret_hash')
            ->json('data');

        return ['id' => (string) $data['id'], 'key' => (string) $data['key'], 'owner' => $owner];
    }

    public function test_create_returns_full_key_once_and_list_hides_it(): void
    {
        $created = $this->createKey();

        $this->assertStringStartsWith('unero_live_', $created['key']);
        $this->assertStringContainsString('.', $created['key']);

        $this->getJson('/identity/api-keys?owner_type=service_account&owner_id='.$created['owner'], $this->admin)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'CI deploy')
            ->assertJsonMissingPath('data.0.key')
            ->assertJsonMissingPath('data.0.secret_hash');
    }

    public function test_revoke_marks_revoked(): void
    {
        $created = $this->createKey();

        $this->deleteJson("/identity/api-keys/{$created['id']}", [], $this->admin + ['Idempotency-Key' => 'r1'])
            ->assertOk()
            ->assertJsonPath('data.revoked', true)
            ->assertJsonPath('data.id', $created['id']);
    }

    public function test_create_for_unknown_service_account_owner_is_404(): void
    {
        $this->postJson('/identity/api-keys', [
            'name' => 'x', 'owner_type' => 'service_account', 'owner_id' => (string) new Ulid, 'scopes' => [],
        ], $this->admin + ['Idempotency-Key' => 'k1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SERVICE_001');
    }

    public function test_create_for_unknown_user_owner_is_404(): void
    {
        $this->postJson('/identity/api-keys', [
            'name' => 'x', 'owner_type' => 'user', 'owner_id' => (string) new Ulid, 'scopes' => [],
        ], $this->admin + ['Idempotency-Key' => 'k1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_001');
    }

    public function test_revoke_unknown_key_is_404(): void
    {
        $this->deleteJson('/identity/api-keys/'.new Ulid, [], $this->admin + ['Idempotency-Key' => 'r1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'APIKEY_001');
    }

    public function test_invalid_scope_is_422(): void
    {
        $owner = $this->ownerServiceAccountId();

        $this->postJson('/identity/api-keys', [
            'name' => 'x', 'owner_type' => 'service_account', 'owner_id' => $owner, 'scopes' => ['NOT VALID'],
        ], $this->admin + ['Idempotency-Key' => 'k1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }

    public function test_surface_requires_actor(): void
    {
        $this->getJson('/identity/api-keys')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }
}
