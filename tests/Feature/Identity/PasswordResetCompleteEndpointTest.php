<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class PasswordResetCompleteEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $svc = ['X-Actor-Id' => 'notification-svc', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function register(string $email = 'ada@unero.com', string $password = 'S3cret-pass'): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => $email, 'username' => 'ada_l', 'password' => $password,
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');
    }

    /** Drive request -> materialize and return the raw reset token. */
    private function obtainResetToken(string $email, string $userId): string
    {
        $this->postJson('/identity/auth/password/reset-request', ['email' => $email])->assertStatus(202);
        $ref = (string) DB::table('password_resets')->where('user_id', $userId)->value('delivery_ref');

        return (string) $this->postJson("/identity/internal/password-reset/deliveries/{$ref}/materialize", [], $this->svc)
            ->assertOk()->json('data.token');
    }

    public function test_reset_changes_password_and_revokes_sessions(): void
    {
        $userId = $this->register('ada@unero.com', 'S3cret-pass');

        $refreshToken = (string) $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.refresh_token');

        $token = $this->obtainResetToken('ada@unero.com', $userId);

        $this->postJson('/identity/auth/password/reset', ['token' => $token, 'new_password' => 'BrandNew-pass9'])
            ->assertOk()
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.reset', true);

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'PasswordChanged', 'aggregate_type' => 'User']);
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'TokenRevoked']);

        // old password no longer works; new one does
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertStatus(401)->assertJsonPath('error.code', 'AUTH_002');
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'BrandNew-pass9'])
            ->assertOk();

        // the pre-reset refresh session is dead
        $this->postJson('/identity/auth/refresh', ['refresh_token' => $refreshToken])
            ->assertStatus(401);
    }

    public function test_invalid_token_is_400(): void
    {
        $this->postJson('/identity/auth/password/reset', ['token' => 'nope', 'new_password' => 'BrandNew-pass9'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'RESET_002');
    }

    public function test_weak_password_is_422(): void
    {
        $userId = $this->register('ada@unero.com');
        $token = $this->obtainResetToken('ada@unero.com', $userId);

        $this->postJson('/identity/auth/password/reset', ['token' => $token, 'new_password' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }

    public function test_token_cannot_be_reused(): void
    {
        $userId = $this->register('ada@unero.com');
        $token = $this->obtainResetToken('ada@unero.com', $userId);

        $this->postJson('/identity/auth/password/reset', ['token' => $token, 'new_password' => 'BrandNew-pass9'])->assertOk();

        $this->postJson('/identity/auth/password/reset', ['token' => $token, 'new_password' => 'AnotherNew-pass9'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'RESET_002');
    }
}
