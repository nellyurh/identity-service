<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class ServiceAccountEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    /** @return array{id:string,secret:string} */
    private function create(string $name = 'wallet', string $key = 'c1'): array
    {
        $data = $this->postJson('/identity/service-accounts', [
            'name' => $name, 'scopes' => ['wallet.credit', 'wallet.debit'],
        ], $this->admin + ['Idempotency-Key' => $key])
            ->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.status', 'active')
            ->json('data');

        return ['id' => (string) $data['id'], 'secret' => (string) $data['secret']];
    }

    public function test_create_returns_secret_once_and_list_hides_it(): void
    {
        $created = $this->create();
        $this->assertNotSame('', $created['secret']);

        $this->getJson('/identity/service-accounts', $this->admin)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'wallet')
            ->assertJsonMissingPath('data.0.secret');

        $this->getJson("/identity/service-accounts/{$created['id']}", $this->admin)
            ->assertOk()
            ->assertJsonMissingPath('data.secret')
            ->assertJsonPath('data.scopes', ['wallet.credit', 'wallet.debit']);
    }

    public function test_rotate_issues_a_new_secret(): void
    {
        $created = $this->create();

        $rotated = $this->postJson("/identity/service-accounts/{$created['id']}/rotate", [], $this->admin + ['Idempotency-Key' => 'r1'])
            ->assertOk()
            ->json('data.secret');

        $this->assertNotSame('', (string) $rotated);
        $this->assertNotSame($created['secret'], $rotated);
    }

    public function test_disable_marks_disabled(): void
    {
        $created = $this->create();

        $this->postJson("/identity/service-accounts/{$created['id']}/disable", [], $this->admin + ['Idempotency-Key' => 'd1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');
    }

    public function test_duplicate_name_is_409(): void
    {
        $this->create('wallet', 'c1');

        $this->postJson('/identity/service-accounts', ['name' => 'wallet', 'scopes' => []], $this->admin + ['Idempotency-Key' => 'c2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SERVICE_003');
    }

    public function test_invalid_scope_is_422(): void
    {
        $this->postJson('/identity/service-accounts', ['name' => 'wallet', 'scopes' => ['NOPE bad']], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }

    public function test_unknown_account_rotate_is_404(): void
    {
        $this->postJson('/identity/service-accounts/'.new Ulid.'/rotate', [], $this->admin + ['Idempotency-Key' => 'r1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SERVICE_001');
    }

    public function test_surface_requires_actor(): void
    {
        $this->getJson('/identity/service-accounts')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }
}
