<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class ApiKeyRotateEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('apikey:wallet.read')->get('/test/apikey-guarded', static fn () => response()->json(['ok' => true]));
    }

    /** @return array{id:string,key:string,owner:string} */
    private function createKey(): array
    {
        $owner = (string) $this->postJson('/identity/service-accounts', [
            'name' => 'wallet', 'scopes' => ['wallet.credit'],
        ], $this->admin + ['Idempotency-Key' => 'sa1'])->assertCreated()->json('data.id');

        $data = $this->postJson('/identity/api-keys', [
            'name' => 'CI', 'owner_type' => 'service_account', 'owner_id' => $owner, 'scopes' => ['wallet.read'],
        ], $this->admin + ['Idempotency-Key' => 'k1'])->assertCreated()->json('data');

        return ['id' => (string) $data['id'], 'key' => (string) $data['key'], 'owner' => $owner];
    }

    public function test_rotate_issues_new_key_and_old_stays_valid_during_grace(): void
    {
        $old = $this->createKey();

        $rotated = $this->postJson("/identity/api-keys/{$old['id']}/rotate", [], $this->admin + ['Idempotency-Key' => 'rot1'])
            ->assertOk()
            ->assertJsonPath('data.replaced_key_id', $old['id'])
            ->json('data');

        $newKey = (string) $rotated['key'];
        $this->assertStringStartsWith('unero_live_', $newKey);
        $this->assertNotSame($old['id'], (string) $rotated['id']);
        $this->assertNotNull($rotated['grace_expires_at']);

        // both the new key and the old key (within grace) authenticate
        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$newKey])->assertOk();
        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$old['key']])->assertOk();

        // owner now has two keys
        $this->getJson('/identity/api-keys?owner_type=service_account&owner_id='.$old['owner'], $this->admin)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rotating_a_revoked_key_is_409(): void
    {
        $key = $this->createKey();
        $this->deleteJson("/identity/api-keys/{$key['id']}", [], $this->admin + ['Idempotency-Key' => 'r1'])->assertOk();

        $this->postJson("/identity/api-keys/{$key['id']}/rotate", [], $this->admin + ['Idempotency-Key' => 'rot1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'APIKEY_004');
    }

    public function test_rotating_unknown_key_is_404(): void
    {
        $this->postJson('/identity/api-keys/'.new Ulid.'/rotate', [], $this->admin + ['Idempotency-Key' => 'rot1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'APIKEY_001');
    }
}
