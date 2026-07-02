<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class PasswordResetRequestEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $svc = ['X-Actor-Id' => 'notification-svc', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function registerUser(string $email = 'ada@unero.com'): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => $email, 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');
    }

    private function deliveryRefFor(string $userId): string
    {
        return (string) DB::table('password_resets')->where('user_id', $userId)->value('delivery_ref');
    }

    public function test_request_for_known_email_is_202_and_emits_event(): void
    {
        $userId = $this->registerUser('ada@unero.com');

        $this->postJson('/identity/auth/password/reset-request', ['email' => 'ada@unero.com'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'PasswordResetRequested']);
        $this->assertDatabaseHas('password_resets', ['user_id' => $userId]);
    }

    public function test_request_for_unknown_email_is_202_without_event(): void
    {
        $this->postJson('/identity/auth/password/reset-request', ['email' => 'nobody@unero.com'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseMissing('outbox_entries', ['event_type' => 'PasswordResetRequested']);
        $this->assertSame(0, DB::table('password_resets')->count());
    }

    public function test_malformed_email_is_422(): void
    {
        $this->postJson('/identity/auth/password/reset-request', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }

    public function test_materialize_returns_token_and_email(): void
    {
        $userId = $this->registerUser('ada@unero.com');
        $this->postJson('/identity/auth/password/reset-request', ['email' => 'ada@unero.com'])->assertStatus(202);
        $ref = $this->deliveryRefFor($userId);

        $this->postJson("/identity/internal/password-reset/deliveries/{$ref}/materialize", [], $this->svc)
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@unero.com')
            ->assertJsonStructure(['data' => ['email', 'token', 'expires_at']]);
    }

    public function test_materialize_is_single_use(): void
    {
        $userId = $this->registerUser('ada@unero.com');
        $this->postJson('/identity/auth/password/reset-request', ['email' => 'ada@unero.com'])->assertStatus(202);
        $ref = $this->deliveryRefFor($userId);

        $this->postJson("/identity/internal/password-reset/deliveries/{$ref}/materialize", [], $this->svc)->assertOk();

        $this->postJson("/identity/internal/password-reset/deliveries/{$ref}/materialize", [], $this->svc)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RESET_001');
    }

    public function test_materialize_unknown_ref_is_404(): void
    {
        $this->postJson('/identity/internal/password-reset/deliveries/deadbeefdeadbeef/materialize', [], $this->svc)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RESET_001');
    }

    public function test_materialize_requires_actor(): void
    {
        $this->postJson('/identity/internal/password-reset/deliveries/deadbeefdeadbeef/materialize')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }
}
