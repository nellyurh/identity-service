<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TotpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTotpProvider;
use Tests\TestCase;

final class MfaRecoveryLoginTest extends TestCase
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

    /** @return list<string> the recovery codes */
    private function registerAndEnable(): array
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');

        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => 'e1'])->assertOk();

        /** @var list<string> $codes */
        $codes = $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])
            ->assertOk()->json('data.recovery_codes');

        return $codes;
    }

    private function challenge(): string
    {
        return (string) $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.challenge_token');
    }

    public function test_recovery_code_completes_login(): void
    {
        $codes = $this->registerAndEnable();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $this->challenge(), 'recovery_code' => $codes[0]])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }

    public function test_recovery_code_is_single_use(): void
    {
        $codes = $this->registerAndEnable();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $this->challenge(), 'recovery_code' => $codes[0]])->assertOk();

        // a fresh challenge, same code — now spent
        $this->postJson('/identity/login/mfa', ['challenge_token' => $this->challenge(), 'recovery_code' => $codes[0]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MFA_002');
    }

    public function test_unknown_recovery_code_is_422(): void
    {
        $this->registerAndEnable();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $this->challenge(), 'recovery_code' => 'ffffffffff'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MFA_002');
    }

    public function test_totp_code_still_works_alongside_recovery(): void
    {
        $this->registerAndEnable();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $this->challenge(), 'code' => FakeTotpProvider::VALID_CODE])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    public function test_neither_factor_is_422(): void
    {
        $this->registerAndEnable();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $this->challenge()])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_422');
    }
}
