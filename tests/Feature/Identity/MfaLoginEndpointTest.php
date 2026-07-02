<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TotpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTotpProvider;
use Tests\TestCase;

final class MfaLoginEndpointTest extends TestCase
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

    private function register(string $email, string $username): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => $email, 'username' => $username, 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-'.$username])->assertCreated()->json('data.user_id');
    }

    private function enableMfa(string $userId): void
    {
        $this->postJson("/identity/users/{$userId}/mfa/totp/enroll", [], $this->admin + ['Idempotency-Key' => 'e-'.$userId])->assertOk();
        $this->postJson("/identity/users/{$userId}/mfa/totp/confirm", ['code' => FakeTotpProvider::VALID_CODE], $this->admin + ['Idempotency-Key' => 'c-'.$userId])->assertOk();
    }

    private function login(string $email = 'ada@unero.com'): TestResponse
    {
        return $this->postJson('/identity/login', ['email' => $email, 'password' => 'S3cret-pass']);
    }

    public function test_mfa_user_gets_challenge_then_completes_to_tokens(): void
    {
        $userId = $this->register('ada@unero.com', 'ada_l');
        $this->enableMfa($userId);

        $challengeToken = (string) $this->login()
            ->assertOk()
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonMissingPath('data.access_token')
            ->json('data.challenge_token');

        $this->postJson('/identity/login/mfa', ['challenge_token' => $challengeToken, 'code' => FakeTotpProvider::VALID_CODE])
            ->assertOk()
            ->assertJsonPath('data.user_id', $userId)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in']]);
    }

    public function test_non_mfa_user_logs_in_directly(): void
    {
        $this->register('bob@unero.com', 'bob_k');

        $this->postJson('/identity/login', ['email' => 'bob@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()
            ->assertJsonMissingPath('data.mfa_required')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }

    public function test_wrong_code_is_422(): void
    {
        $userId = $this->register('ada@unero.com', 'ada_l');
        $this->enableMfa($userId);
        $challengeToken = (string) $this->login()->json('data.challenge_token');

        $this->postJson('/identity/login/mfa', ['challenge_token' => $challengeToken, 'code' => '999999'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MFA_002');
    }

    public function test_invalid_challenge_is_401(): void
    {
        $this->postJson('/identity/login/mfa', ['challenge_token' => 'not-a-real-challenge', 'code' => FakeTotpProvider::VALID_CODE])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'MFA_004');
    }

    public function test_challenge_is_single_use(): void
    {
        $userId = $this->register('ada@unero.com', 'ada_l');
        $this->enableMfa($userId);
        $challengeToken = (string) $this->login()->json('data.challenge_token');

        $this->postJson('/identity/login/mfa', ['challenge_token' => $challengeToken, 'code' => FakeTotpProvider::VALID_CODE])->assertOk();

        $this->postJson('/identity/login/mfa', ['challenge_token' => $challengeToken, 'code' => FakeTotpProvider::VALID_CODE])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'MFA_004');
    }
}
