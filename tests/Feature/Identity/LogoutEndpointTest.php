<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class LogoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function loginRefreshToken(): string
    {
        $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated();

        return (string) $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.refresh_token');
    }

    public function test_logout_revokes_the_family_so_refresh_is_rejected(): void
    {
        $refresh = $this->loginRefreshToken();

        $this->postJson('/identity/auth/logout', ['refresh_token' => $refresh])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user_id']]);

        $this->postJson('/identity/auth/refresh', ['refresh_token' => $refresh])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_011');
    }

    public function test_logout_is_idempotent(): void
    {
        $refresh = $this->loginRefreshToken();

        $this->postJson('/identity/auth/logout', ['refresh_token' => $refresh])->assertOk();
        $this->postJson('/identity/auth/logout', ['refresh_token' => $refresh])->assertOk();
    }

    public function test_unknown_token_logout_is_401(): void
    {
        $this->postJson('/identity/auth/logout', ['refresh_token' => str_repeat('b', 64)])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_011');
    }
}
