<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class RefreshEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function login(): TestResponse
    {
        $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated();

        return $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])->assertOk();
    }

    public function test_login_returns_a_refresh_token(): void
    {
        $this->login()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['refresh_token', 'refresh_expires_in']]);
    }

    public function test_refresh_rotates_the_token_pair(): void
    {
        $first = (string) $this->login()->json('data.refresh_token');

        $response = $this->postJson('/identity/auth/refresh', ['refresh_token' => $first])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in', 'refresh_expires_in']]);

        $second = (string) $response->json('data.refresh_token');
        $this->assertNotSame($first, $second);

        // The rotated (new) token works.
        $this->postJson('/identity/auth/refresh', ['refresh_token' => $second])->assertOk();
    }

    public function test_reusing_a_rotated_token_revokes_the_family(): void
    {
        $first = (string) $this->login()->json('data.refresh_token');
        $second = (string) $this->postJson('/identity/auth/refresh', ['refresh_token' => $first])
            ->assertOk()->json('data.refresh_token');

        // Replaying the already-rotated first token is reuse.
        $this->postJson('/identity/auth/refresh', ['refresh_token' => $first])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_012');

        // The whole family is now dead — even the previously-valid second token is refused.
        $this->postJson('/identity/auth/refresh', ['refresh_token' => $second])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_011');
    }

    public function test_unknown_refresh_token_is_401(): void
    {
        $this->postJson('/identity/auth/refresh', ['refresh_token' => str_repeat('a', 64)])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_011');
    }
}
