<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TotpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTotpProvider;
use Tests\TestCase;

final class MfaDisableEndpointTest extends TestCase
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

    private function registerWithMfa(): string
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');

        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => 'e1'])->assertOk();
        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])->assertOk();

        return $userId;
    }

    public function test_disable_turns_off_mfa_and_login_stops_challenging(): void
    {
        $userId = $this->registerWithMfa();

        // MFA is on: login challenges
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertJsonPath('data.mfa_required', true);

        $this->postJson("/identity/users/{$userId}/mfa/totp/disable", [], $this->admin + ['Idempotency-Key' => 'd1'])
            ->assertOk()
            ->assertJsonPath('data.disabled', true);

        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'MFADisabled', 'aggregate_type' => 'TotpCredential']);

        // MFA is off: login returns tokens directly
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()
            ->assertJsonMissingPath('data.mfa_required')
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    public function test_disable_is_idempotent(): void
    {
        $userId = $this->registerWithMfa();

        $this->postJson("/identity/users/{$userId}/mfa/totp/disable", [], $this->admin + ['Idempotency-Key' => 'd1'])
            ->assertOk()->assertJsonPath('data.disabled', true);

        $this->postJson("/identity/users/{$userId}/mfa/totp/disable", [], $this->admin + ['Idempotency-Key' => 'd2'])
            ->assertOk()->assertJsonPath('data.disabled', false);
    }

    public function test_disable_without_mfa_reports_false(): void
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'bob@unero.com', 'username' => 'bob_k', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-2'])->assertCreated()->json('data.user_id');

        $this->postJson("/identity/users/{$userId}/mfa/totp/disable", [], $this->admin + ['Idempotency-Key' => 'd1'])
            ->assertOk()
            ->assertJsonPath('data.disabled', false);
    }
}
