<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TotpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTotpProvider;
use Tests\TestCase;

final class MfaEnrollmentEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
        $this->app->bind(TotpProvider::class, static fn (): FakeTotpProvider => new FakeTotpProvider);
    }

    private function registerUser(): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');
    }

    private function enroll(string $userId, string $key = 'e1'): void
    {
        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => $key])
            ->assertOk()
            ->assertJsonPath('data.secret', FakeTotpProvider::SECRET)
            ->assertJsonStructure(['data' => ['secret', 'provisioning_uri']]);
    }

    public function test_enroll_then_confirm_enables_mfa(): void
    {
        $userId = $this->registerUser();
        $this->enroll($userId);

        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'MFAEnabled', 'aggregate_type' => 'TotpCredential']);
        $this->assertDatabaseHas('totp_credentials', ['user_id' => $userId, 'status' => 'active']);
    }

    public function test_confirm_with_wrong_code_is_422(): void
    {
        $userId = $this->registerUser();
        $this->enroll($userId);

        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => '999999'], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MFA_002');
    }

    public function test_confirm_without_enrollment_is_404(): void
    {
        $userId = $this->registerUser();

        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MFA_003');
    }

    public function test_enrolling_when_already_active_is_409(): void
    {
        $userId = $this->registerUser();
        $this->enroll($userId, 'e1');
        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])->assertOk();

        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => 'e2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'MFA_001');
    }

    public function test_malformed_code_is_422(): void
    {
        $userId = $this->registerUser();
        $this->enroll($userId);

        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => 'abc'], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }
}
