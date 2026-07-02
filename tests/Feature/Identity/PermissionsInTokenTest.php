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
use Tests\Support\FakePasswordHasher;
use Tests\TestCase;

final class PermissionsInTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PasswordHasher::class, static fn (): FakePasswordHasher => new FakePasswordHasher);
    }

    private function registerAndGrantUserRead(): string
    {
        $userId = (string) $this->postJson('/identity/register', [
            'email' => 'ada@unero.com', 'username' => 'ada_l', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->json('data.user_id');

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

        return $userId;
    }

    /** @return array<string,mixed> */
    private function decodeClaims(string $jwt): array
    {
        $segments = explode('.', $jwt);
        $json = base64_decode(strtr($segments[1], '-_', '+/'), true);
        $claims = json_decode((string) $json, true);

        return is_array($claims) ? $claims : [];
    }

    public function test_access_token_carries_permissions_and_authz_ver(): void
    {
        $this->registerAndGrantUserRead();

        $token = (string) $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.access_token');

        $claims = $this->decodeClaims($token);

        $this->assertSame(['user.read'], $claims['permissions']);
        $this->assertSame(2, $claims['authz_ver']);
    }

    public function test_introspection_returns_permissions_and_authz_ver(): void
    {
        $this->registerAndGrantUserRead();

        $token = (string) $this->postJson('/identity/login', ['email' => 'ada@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.access_token');

        $this->postJson('/identity/tokens/introspect', ['token' => $token], ['X-Actor-Id' => 'svc-1', 'X-Actor-Type' => 'service'])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.permissions', ['user.read'])
            ->assertJsonPath('data.authz_ver', 2);
    }

    public function test_user_without_roles_gets_empty_permissions(): void
    {
        $this->postJson('/identity/register', [
            'email' => 'bob@unero.com', 'username' => 'bob_x', 'password' => 'S3cret-pass',
        ], ['Idempotency-Key' => 'reg-1'])->assertCreated();

        $token = (string) $this->postJson('/identity/login', ['email' => 'bob@unero.com', 'password' => 'S3cret-pass'])
            ->assertOk()->json('data.access_token');

        $claims = $this->decodeClaims($token);

        $this->assertSame([], $claims['permissions']);
        $this->assertSame(1, $claims['authz_ver']);
    }
}
