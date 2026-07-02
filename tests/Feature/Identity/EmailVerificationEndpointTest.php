<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class EmailVerificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function registerUser(string $email = 'ada@unero.com', string $username = 'ada_l'): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => $email, 'username' => $username, 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-'.$username])->assertCreated()->json('data.user_id');
    }

    private function requestToken(string $userId, string $idem = 'v1'): string
    {
        return (string) $this->postJson("/identity/users/{$userId}/email/verification-request", [], $this->admin + ['Idempotency-Key' => $idem])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_at']])
            ->json('data.token');
    }

    public function test_request_then_verify_marks_email_verified(): void
    {
        $userId = $this->registerUser();
        $token = $this->requestToken($userId);

        $this->postJson('/identity/email/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonPath('data.verified', true);

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'EmailVerified', 'aggregate_type' => 'User']);

        $this->getJson("/identity/users/{$userId}", $this->admin)
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);
    }

    public function test_token_is_single_use(): void
    {
        $userId = $this->registerUser();
        $token = $this->requestToken($userId);

        $this->postJson('/identity/email/verify', ['token' => $token])->assertOk();

        $this->postJson('/identity/email/verify', ['token' => $token])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VERIFICATION_001');
    }

    public function test_unknown_token_is_400(): void
    {
        $this->postJson('/identity/email/verify', ['token' => 'not-a-real-token'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VERIFICATION_001');
    }

    public function test_requesting_for_already_verified_user_is_409(): void
    {
        $userId = $this->registerUser();
        $token = $this->requestToken($userId);
        $this->postJson('/identity/email/verify', ['token' => $token])->assertOk();

        $this->postJson("/identity/users/{$userId}/email/verification-request", [], $this->admin + ['Idempotency-Key' => 'v2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'USER_003');
    }

    public function test_requesting_new_token_invalidates_the_previous(): void
    {
        $userId = $this->registerUser();
        $first = $this->requestToken($userId, 'v1');
        $second = $this->requestToken($userId, 'v2');

        $this->assertNotSame($first, $second);

        // the first token is now dead
        $this->postJson('/identity/email/verify', ['token' => $first])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VERIFICATION_001');

        // the newest token still works
        $this->postJson('/identity/email/verify', ['token' => $second])->assertOk();
    }

    public function test_request_for_unknown_user_is_404(): void
    {
        $this->postJson('/identity/users/'.Ulid::generate().'/email/verification-request', [], $this->admin + ['Idempotency-Key' => 'v1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_001');
    }
}
