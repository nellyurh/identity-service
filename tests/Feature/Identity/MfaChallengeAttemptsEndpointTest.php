<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TotpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTotpProvider;
use Tests\TestCase;

final class MfaChallengeAttemptsEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
        $this->app->bind(TotpProvider::class, static fn (): FakeTotpProvider => new FakeTotpProvider);
        config()->set('unero.mfa.max_challenge_attempts', 3); // keep the test tight
    }

    private function challengeToken(): string
    {
        return (string) $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.challenge_token');
    }

    private function setUpUserWithMfa(): void
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated()->json('data.user_id');

        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => 'e1'])->assertOk();
        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c1'])->assertOk();
    }

    public function test_challenge_is_invalidated_after_max_wrong_codes(): void
    {
        $this->setUpUserWithMfa();
        $token = $this->challengeToken();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'code' => '999999'])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'MFA_002');
        }

        // the cap invalidated the challenge: even the CORRECT code is now refused with MFA_004
        $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'code' => FakeTotpProvider::VALID_CODE])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'MFA_004');
    }

    public function test_below_the_cap_a_correct_code_still_wins(): void
    {
        $this->setUpUserWithMfa();
        $token = $this->challengeToken();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'code' => '999999'])->assertStatus(422);
        $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'code' => '888888'])->assertStatus(422);

        $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'code' => FakeTotpProvider::VALID_CODE])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    public function test_wrong_recovery_codes_also_count_toward_the_cap(): void
    {
        $this->setUpUserWithMfa();
        $token = $this->challengeToken();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'recovery_code' => 'ffffffffff'])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'MFA_002');
        }

        $this->postJson('/identity/login/mfa', ['challenge_token' => $token, 'code' => FakeTotpProvider::VALID_CODE])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'MFA_004');
    }
}
