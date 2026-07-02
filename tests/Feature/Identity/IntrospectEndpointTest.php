<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class IntrospectEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $svc = ['X-Actor-Id' => 'svc-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    /** @return array{access:string,refresh:string,user_id:string} */
    private function login(): array
    {
        $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated();

        $data = $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data');

        return [
            'access' => (string) $data['access_token'],
            'refresh' => (string) $data['refresh_token'],
            'user_id' => (string) $data['user_id'],
        ];
    }

    public function test_introspect_reports_a_live_token_active(): void
    {
        $session = $this->login();

        $this->postJson('/identity/tokens/introspect', ['token' => $session['access']], $this->svc)
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.sub', $session['user_id'])
            ->assertJsonPath('data.token_use', 'access');
    }

    public function test_introspect_requires_service_actor(): void
    {
        $session = $this->login();

        $this->postJson('/identity/tokens/introspect', ['token' => $session['access']])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }

    public function test_garbage_token_is_inactive(): void
    {
        $this->postJson('/identity/tokens/introspect', ['token' => 'not.a.jwt'], $this->svc)
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_logout_blacklists_the_access_token(): void
    {
        $session = $this->login();

        // Live before logout.
        $this->postJson('/identity/tokens/introspect', ['token' => $session['access']], $this->svc)
            ->assertJsonPath('data.active', true);

        $this->postJson('/identity/auth/logout', ['refresh_token' => $session['refresh']])->assertOk();

        // Cryptographically still valid, but denied — introspection reflects the revocation.
        $this->postJson('/identity/tokens/introspect', ['token' => $session['access']], $this->svc)
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }
}
