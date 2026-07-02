<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class UserAdminEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,string> */
    private array $admin = ['X-Actor-Id' => 'admin-1', 'X-Actor-Type' => 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function register(string $email = 'ada@unero.com', string $username = 'ada_l', string $key = 'reg'): string
    {
        return (string) $this->postJson('/identity/register', [
            'email' => $email, 'username' => $username, 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => $key])->json('data.user_id');
    }

    public function test_show_returns_profile(): void
    {
        $id = $this->register();

        $this->getJson("/identity/users/{$id}", $this->admin)
            ->assertOk()
            ->assertJsonPath('data.user_id', $id)
            ->assertJsonPath('data.email', 'ada@unero.com')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_show_unknown_is_404(): void
    {
        $this->getJson('/identity/users/'.new Ulid, $this->admin)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'USER_001');
    }

    public function test_admin_surface_requires_actor(): void
    {
        $id = $this->register();

        $this->getJson("/identity/users/{$id}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_001');
    }

    public function test_lookup_by_email(): void
    {
        $this->register();

        $this->getJson('/identity/users?email=ada@unero.com', $this->admin)
            ->assertOk()
            ->assertJsonPath('data.username', 'ada_l');
    }

    public function test_disable_then_enable_round_trip(): void
    {
        $id = $this->register();

        $this->postJson("/identity/users/{$id}/disable", ['reason' => 'policy'], $this->admin + ['Idempotency-Key' => 'dis-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $this->postJson("/identity/users/{$id}/enable", [], $this->admin + ['Idempotency-Key' => 'ena-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_delete_soft_deletes(): void
    {
        $id = $this->register();

        $this->deleteJson("/identity/users/{$id}", [], $this->admin + ['Idempotency-Key' => 'del-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'deleted');
    }

    public function test_self_service_change_password(): void
    {
        $id = $this->register();
        $actor = ['X-Actor-Id' => $id, 'X-Actor-Type' => 'user'];

        $this->postJson('/identity/change-password', [
            'current_password' => 'S3cret-pass', 'new_password' => 'N3w-secret-pass',
        ], $actor + ['Idempotency-Key' => 'cp-1'])->assertOk();

        // old password no longer works, new one does
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertStatus(401);
        $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'N3w-secret-pass'])
            ->assertOk();
    }
}
