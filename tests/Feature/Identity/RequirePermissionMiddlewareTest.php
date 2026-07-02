<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Port\PasswordHasher;
use App\Domain\Identity\Permission\Permission;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Permission\ValueObject\PermissionName;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\Role\Role;
use App\Domain\Identity\Role\ValueObject\RoleName;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class RequirePermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);

        Route::middleware('permission:user.read')->get(
            '/test/guarded',
            static fn () => response()->json(['ok' => true]),
        );
    }

    private function login(string $email): string
    {
        return (string) $this->postJson('/identity/login', ['email' => $email, 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.access_token');
    }

    private function registerPlain(string $email, string $username): void
    {
        $this->postJson('/identity/register', [
            'email' => $email, 'username' => $username, 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-'.$username])->assertCreated();
    }

    private function registerWithUserRead(string $email, string $username): void
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => $email, 'username' => $username, 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-'.$username])->json('data.user_id');

        $now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');

        $permissions = $this->app->make(PermissionRepository::class);
        $this->assertInstanceOf(PermissionRepository::class, $permissions);
        $read = Permission::define($permissions->nextIdentity(), new PermissionName('user.read'), null, true);
        $permissions->save($read);

        $roles = $this->app->make(RoleRepository::class);
        $this->assertInstanceOf(RoleRepository::class, $roles);
        $role = Role::create($roles->nextIdentity(), new RoleName('reader'), null, false, $now);
        $role->grantPermission($read->id, $now);
        $roles->save($role);

        $users = $this->app->make(UserRepository::class);
        $this->assertInstanceOf(UserRepository::class, $users);
        $user = $users->getById(new UserId($userId));
        $user->assignRole($role->id, $now);
        $users->save($user);
    }

    public function test_allows_when_token_has_permission(): void
    {
        $this->registerWithUserRead('ada@unero.com', 'ada_l');
        $token = $this->login('ada@unero.com');

        $this->getJson('/test/guarded', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_denies_with_403_when_permission_absent(): void
    {
        $this->registerPlain('bob@unero.com', 'bob_x');
        $token = $this->login('bob@unero.com');

        $this->getJson('/test/guarded', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTHZ_001');
    }

    public function test_401_when_no_bearer_token(): void
    {
        $this->getJson('/test/guarded')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_010');
    }
}
