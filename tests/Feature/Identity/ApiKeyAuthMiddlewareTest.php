<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ApiKeyAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('apikey:wallet.read')->get(
            '/test/apikey-guarded',
            static fn () => response()->json(['ok' => true]),
        );
    }

    private function ownerId(): string
    {
        return (string) $this->postJson('/identity/service-accounts', [
            'name' => 'wallet', 'scopes' => ['wallet.credit'],
        ], $this->admin + ['Idempotency-Key' => 'sa1'])->assertCreated()->json('data.id');
    }

    /**
     * @param  list<string>  $scopes
     * @return array{id:string,key:string,owner:string}
     */
    private function createKey(array $scopes, string $idem = 'k1', ?string $expiresAt = null): array
    {
        $owner = $this->ownerId();
        $body = ['name' => 'k', 'owner_type' => 'service_account', 'owner_id' => $owner, 'scopes' => $scopes];
        if ($expiresAt !== null) {
            $body['expires_at'] = $expiresAt;
        }

        $data = $this->postJson('/identity/api-keys', $body, $this->admin + ['Idempotency-Key' => $idem])
            ->assertCreated()->json('data');

        return ['id' => (string) $data['id'], 'key' => (string) $data['key'], 'owner' => $owner];
    }

    public function test_valid_key_with_scope_passes(): void
    {
        $k = $this->createKey(['wallet.read']);

        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$k['key']])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_missing_header_is_401(): void
    {
        $this->getJson('/test/apikey-guarded')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'APIKEY_003');
    }

    public function test_wrong_secret_is_401(): void
    {
        $k = $this->createKey(['wallet.read']);
        $tampered = substr($k['key'], 0, -1).($k['key'][-1] === 'a' ? 'b' : 'a');

        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$tampered])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'APIKEY_003');
    }

    public function test_insufficient_scope_is_403(): void
    {
        $k = $this->createKey(['billing.read']);

        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$k['key']])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'APIKEY_002');
    }

    public function test_revoked_key_is_401(): void
    {
        $k = $this->createKey(['wallet.read']);
        $this->deleteJson("/identity/api-keys/{$k['id']}", [], $this->admin + ['Idempotency-Key' => 'r1'])->assertOk();

        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$k['key']])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'APIKEY_003');
    }

    public function test_expired_key_is_401(): void
    {
        $k = $this->createKey(['wallet.read'], 'k1', '2020-01-01T00:00:00+00:00');

        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$k['key']])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'APIKEY_003');
    }

    public function test_successful_use_records_last_used_at(): void
    {
        $k = $this->createKey(['wallet.read']);

        $this->getJson('/test/apikey-guarded', ['Authorization' => 'ApiKey '.$k['key']])->assertOk();

        $lastUsed = $this->getJson('/identity/api-keys?owner_type=service_account&owner_id='.$k['owner'], $this->admin)
            ->assertOk()
            ->json('data.0.last_used_at');

        $this->assertNotNull($lastUsed);
    }
}
