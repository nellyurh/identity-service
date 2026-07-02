<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class LoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function register(): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->json('data.user_id');
    }

    public function test_valid_login_returns_principal(): void
    {
        $userId = $this->register();

        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_wrong_password_is_401_without_enumeration(): void
    {
        $this->register();

        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'wrong-pass'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_002');
    }

    public function test_unknown_email_is_identical_401(): void
    {
        $this->postJson('/identity/login', ['email' => 'ghost@unero.com', 'password' => 'whatever'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_002');
    }
}
