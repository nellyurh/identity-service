<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class LoginLockoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
        config()->set('unero.lockout.max_attempts', 3); // keep the test fast
    }

    private function register(): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');
    }

    private function failLogin(): void
    {
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'wrong-pass'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_002');
    }

    public function test_account_locks_after_threshold_and_refuses_correct_password(): void
    {
        $userId = $this->register();

        $this->failLogin();
        $this->failLogin();
        $this->failLogin(); // third failure trips the lock

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'UserLocked', 'aggregate_type' => 'User']);
        $this->assertDatabaseMissing('users', ['id' => $userId, 'locked_until' => null]);

        // the CORRECT password is now refused with the same generic error
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_002');
    }

    public function test_below_threshold_login_succeeds_and_resets(): void
    {
        $userId = $this->register();

        $this->failLogin();
        $this->failLogin();

        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token']]);

        $this->assertDatabaseHas('users', ['id' => $userId, 'failed_login_count' => 0]);
        $this->assertDatabaseMissing('outbox_entries', ['event_type' => 'UserLocked']);
    }
}
